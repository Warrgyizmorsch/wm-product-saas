@extends('layouts.duralux')

@section('title', 'Travel & Expense Management | SaaS ERP')
@section('page-title', 'Travel & Expenses')
@section('breadcrumb', 'HRMS / Operations / Travel & Expenses')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
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
            vertical-align: middle !important;
            background-color: transparent !important;
        }

        /* Odoo Table Form Input & Select Custom Styling */
        .odoo-table-input, .odoo-table-select {
            border: none !important;
            border-bottom: 1px solid #ced4da !important;
            border-radius: 0 !important;
            padding: 5px 0 !important;
            background-color: transparent !important;
            font-size: 13px !important;
            color: #212529 !important;
            width: 100% !important;
            transition: border-color 0.2s ease-in-out !important;
        }
        .odoo-table-input:hover, .odoo-table-select:hover {
            border-bottom-color: #64748b !important;
        }
        .odoo-table-input:focus, .odoo-table-select:focus {
            border-bottom-color: var(--bs-primary) !important;
            outline: none !important;
            box-shadow: none !important;
        }

        /* Custom dropdown arrow for table select */
        select.odoo-table-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 4px center !important;
            background-size: 10px 10px !important;
            padding-right: 15px !important;
        }

        /* Custom Receipt File Input Wrapper Styles */
        .odoo-table-file-label {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 6px 12px !important;
            border: 1px dashed #cbd5e1 !important;
            border-radius: 6px !important;
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            width: 100% !important;
            justify-content: center !important;
        }
        .odoo-table-file-label:hover {
            background-color: #f1f5f9 !important;
            border-color: #94a3b8 !important;
            color: #1e293b !important;
        }
        .odoo-table-file-label i {
            font-size: 13px !important;
            color: #64748b !important;
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
                        <tbody id="travelTableBody">
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
                                    <td class="fw-semibold">
                                        @if($req->status === 'approved' && $req->approved_budget)
                                            @if($req->approved_budget < $req->estimated_budget)
                                                <div class="text-success">{{ $currencySymbol }}{{ number_format($req->approved_budget, 2) }}</div>
                                                <div class="text-muted fs-11 text-decoration-line-through">Requested: {{ $currencySymbol }}{{ number_format($req->estimated_budget, 2) }}</div>
                                            @else
                                                <div>{{ $currencySymbol }}{{ number_format($req->approved_budget, 2) }}</div>
                                            @endif
                                        @else
                                            <div>{{ $currencySymbol }}{{ number_format($req->estimated_budget, 2) }}</div>
                                        @endif
                                    </td>
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
                                                <button type="button" class="btn btn-sm btn-soft-success py-1 fw-bold fs-11" data-bs-toggle="modal" data-bs-target="#approveTravelModal_{{ $req->id }}">Approve</button>
                                                <form method="POST" action="{{ route('hrms.travel-expense.travel.reject', $req) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger py-1 fw-bold fs-11">Reject</button>
                                                </form>
                                            </div>

                                            <!-- Modal to Approve Travel Request with Custom Budget -->
                                            <div class="modal fade text-start" id="approveTravelModal_{{ $req->id }}" tabindex="-1" aria-labelledby="approveTravelModalLabel_{{ $req->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
                                                    <div class="modal-content text-dark">
                                                        <form method="POST" action="{{ route('hrms.travel-expense.travel.approve', $req) }}">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h6 class="modal-title fw-bold" id="approveTravelModalLabel_{{ $req->id }}"><i class="feather-check-circle text-success me-1"></i> Approve Travel Request</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body d-flex flex-column gap-3">
                                                                <p class="fs-12 text-muted mb-0">Specify the approved budget for this trip. You can partially approve the budget by entering a lower amount.</p>
                                                                
                                                                <div class="p-3 bg-light rounded border">
                                                                    <div class="fs-11 text-muted">Requested Budget</div>
                                                                    <div class="fw-bold fs-15 text-dark">{{ $currencySymbol }}{{ number_format($req->estimated_budget, 2) }}</div>
                                                                </div>

                                                                <x-ui.odoo-form-ui type="input" inputType="number" label="Approved Budget" name="approved_budget" value="{{ $req->estimated_budget }}" step="0.01" min="0.01" :required="true" />
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light border btn-sm text-uppercase fw-bold fs-11" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success btn-sm text-uppercase fw-bold fs-11">Approve</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
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
                @if($travelRequests->hasPages())
                    <div class="mt-4 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted fs-12">
                            Showing {{ $travelRequests->firstItem() ?? 0 }} to {{ $travelRequests->lastItem() ?? 0 }} of {{ $travelRequests->total() }} records
                        </div>
                        <div class="d-flex justify-content-end">
                            {{ $travelRequests->links() }}
                        </div>
                    </div>
                @endif
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
                        <tbody id="advanceTableBody">
                            @forelse($cashAdvances as $adv)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $adv->employee->full_name }}</div>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        @if(($adv->status === 'approved' || $adv->status === 'disbursed' || $adv->status === 'settled') && $adv->approved_amount)
                                            @if($adv->approved_amount != $adv->amount)
                                                <div class="text-success">{{ $currencySymbol }}{{ number_format($adv->approved_amount, 2) }}</div>
                                                <div class="text-muted fs-11 text-decoration-line-through">Requested: {{ $currencySymbol }}{{ number_format($adv->amount, 2) }}</div>
                                            @else
                                                <div>{{ $currencySymbol }}{{ number_format($adv->approved_amount, 2) }}</div>
                                            @endif
                                        @else
                                            <div>{{ $currencySymbol }}{{ number_format($adv->amount, 2) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-dark">{{ $adv->purpose }}</div>
                                        @if($adv->travelRequest)
                                            <div class="text-muted fs-11"><i class="feather-map me-1"></i>Trip to {{ $adv->travelRequest->destination }} ({{ $adv->travelRequest->purpose }})</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($adv->status === 'disbursed')
                                            <span class="badge bg-soft-info text-info px-2 py-1 fs-11 rounded-pill">Disbursed</span>
                                        @elseif($adv->status === 'approved')
                                            <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill">Approved</span>
                                        @elseif($adv->status === 'settled')
                                            <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill">Settled</span>
                                        @elseif($adv->status === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2 py-1 fs-11 rounded-pill">Rejected</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 rounded-pill">Pending</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if($adv->status === 'pending')
                                            <div class="d-flex justify-content-end gap-1">
                                                <button type="button" class="btn btn-sm btn-soft-success py-1 fw-bold fs-11" data-bs-toggle="modal" data-bs-target="#approveAdvanceModal_{{ $adv->id }}">Approve</button>
                                                <form method="POST" action="{{ route('hrms.travel-expense.advance.reject', $adv) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger py-1 fw-bold fs-11">Reject</button>
                                                </form>
                                            </div>

                                            <!-- Modal to Approve Cash Advance with Custom Amount -->
                                            <div class="modal fade text-start" id="approveAdvanceModal_{{ $adv->id }}" tabindex="-1" aria-labelledby="approveAdvanceModalLabel_{{ $adv->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
                                                    <div class="modal-content text-dark">
                                                        <form method="POST" action="{{ route('hrms.travel-expense.advance.approve', $adv) }}">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h6 class="modal-title fw-bold" id="approveAdvanceModalLabel_{{ $adv->id }}"><i class="feather-check-circle text-success me-1"></i> Approve Cash Advance</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body d-flex flex-column gap-3">
                                                                <p class="fs-12 text-muted mb-0">Specify the approved cash advance amount. You can partially approve a lower amount or adjust as needed.</p>
                                                                
                                                                <div class="p-3 bg-light rounded border">
                                                                    <div class="fs-11 text-muted">Requested Amount</div>
                                                                    <div class="fw-bold fs-15 text-dark">{{ $currencySymbol }}{{ number_format($adv->amount, 2) }}</div>
                                                                </div>

                                                                <x-ui.odoo-form-ui type="input" inputType="number" label="Approved Amount" name="approved_amount" value="{{ $adv->amount }}" step="0.01" min="1" :required="true" />
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light border btn-sm text-uppercase fw-bold fs-11" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success btn-sm text-uppercase fw-bold fs-11">Approve</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif($adv->status === 'approved')
                                            <div class="d-flex justify-content-end gap-1">
                                                <form method="POST" action="{{ route('hrms.travel-expense.advance.disburse', $adv) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-primary py-1 fw-bold fs-11"><i class="feather-dollar-sign me-1"></i>Disburse</button>
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
                @if($cashAdvances->hasPages())
                    <div class="mt-4 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted fs-12">
                            Showing {{ $cashAdvances->firstItem() ?? 0 }} to {{ $cashAdvances->lastItem() ?? 0 }} of {{ $cashAdvances->total() }} records
                        </div>
                        <div class="d-flex justify-content-end">
                            {{ $cashAdvances->links() }}
                        </div>
                    </div>
                @endif
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
                        <tbody id="reportTableBody">
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
                                                    <span class="fw-semibold text-primary">• {{ $claim->category->name }}</span>: {{ $currencySymbol }}{{ number_format($claim->amount, 2) }}
                                                    @if($claim->receipt_path)
                                                        <a href="{{ asset('storage/' . $claim->receipt_path) }}" target="_blank" class="ms-1 text-info fs-10 fw-bold"><i class="feather-image"></i> View Receipt</a>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $fullAdvance = $rep->cashAdvance ? ($rep->cashAdvance->approved_amount ?? $rep->cashAdvance->amount) : 0;
                                            $currentExpense = ($rep->status === 'approved' || $rep->status === 'paid') ? ($rep->approved_amount ?? $rep->total_amount) : $rep->total_amount;
                                            $surplus = $fullAdvance > $currentExpense ? ($fullAdvance - $currentExpense) : 0;
                                        @endphp

                                        @if(($rep->status === 'approved' || $rep->status === 'paid') && $rep->approved_amount)
                                            @if($rep->approved_amount != $rep->total_amount)
                                                <div class="fs-12 text-muted">Approved Expense: <span class="fw-bold text-success">{{ $currencySymbol }}{{ number_format($rep->approved_amount, 2) }}</span></div>
                                                <div class="fs-11 text-muted text-decoration-line-through">Requested: {{ $currencySymbol }}{{ number_format($rep->total_amount, 2) }}</div>
                                            @else
                                                <div class="fs-12 text-muted">Approved Expense: <span class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($rep->approved_amount, 2) }}</span></div>
                                            @endif
                                            <div class="fs-12 text-muted">Advance Adjusted: <span class="text-dark">{{ $currencySymbol }}{{ number_format($rep->advance_adjusted, 2) }}</span></div>
                                            <div class="fs-12 fw-bold text-primary mt-1">Reimbursement: {{ $currencySymbol }}{{ number_format($rep->approved_net_reimbursement ?? $rep->net_reimbursement, 2) }}</div>
                                        @else
                                            <div class="fs-12 text-muted">Total Expense: <span class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($rep->total_amount, 2) }}</span></div>
                                            <div class="fs-12 text-muted">Advance Adjusted: <span class="text-dark">{{ $currencySymbol }}{{ number_format($rep->advance_adjusted, 2) }}</span></div>
                                            <div class="fs-12 fw-bold text-primary mt-1">Reimbursement: {{ $currencySymbol }}{{ number_format($rep->net_reimbursement, 2) }}</div>
                                        @endif

                                        @if($surplus > 0)
                                            <div class="badge bg-soft-warning text-warning mt-1.5 fs-10 px-2 py-1 rounded-pill d-inline-flex align-items-center"><i class="feather-alert-triangle me-1"></i>Refund due: {{ $currencySymbol }}{{ number_format($surplus, 2) }}</div>
                                        @endif
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

                                        @if($rep->status === 'approved' || $rep->status === 'paid')
                                            <div class="mt-1">
                                                @if(($rep->payout_channel ?? 'accounting') === 'payroll')
                                                    <span class="badge bg-soft-info text-info px-1.5 py-0.5 fs-10"><i class="feather-user me-0.5"></i>Payroll</span>
                                                @else
                                                    <span class="badge bg-soft-primary text-primary px-1.5 py-0.5 fs-10"><i class="feather-dollar-sign me-0.5"></i>Accounting</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <x-ui.icon-btn type="button" variant="soft-info" size="sm" class="btn-view-report"
                                                icon="feather-eye"
                                                title="View Details"
                                                data-id="{{ $rep->id }}"
                                                data-title="{{ $rep->title }}"
                                                data-employee="{{ $rep->employee->full_name }}"
                                                data-travel="{{ $rep->travelRequest ? $rep->travelRequest->destination . ' (' . $rep->travelRequest->purpose . ')' : 'None' }}"
                                                data-advance="{{ $rep->cashAdvance ? $currencySymbol . number_format($rep->cashAdvance->approved_amount ?? $rep->cashAdvance->amount, 2) . ' - ' . $rep->cashAdvance->purpose : 'None' }}"
                                                data-total="{{ $currencySymbol }}{{ number_format($rep->total_amount, 2) }}"
                                                data-adjusted="{{ $currencySymbol }}{{ number_format($rep->advance_adjusted, 2) }}"
                                                data-net="{{ $currencySymbol }}{{ number_format($rep->net_reimbursement, 2) }}"
                                                data-status="{{ ucfirst($rep->status) }}"
                                                data-payout-channel="{{ $rep->payout_channel ?? 'accounting' }}"
                                                data-claims="{{ $rep->claims->load('category') }}"
                                                data-full-advance="{{ $fullAdvance }}"
                                                data-current-expense="{{ $currentExpense }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewReportModal"
                                            />

                                            @if($rep->status === 'draft')
                                                <x-ui.icon-btn type="button" variant="soft-primary" size="sm" class="btn-edit-report"
                                                    icon="feather-edit-3"
                                                    title="Edit Report"
                                                    data-id="{{ $rep->id }}"
                                                    data-title="{{ $rep->title }}"
                                                    data-travel-id="{{ $rep->travel_request_id }}"
                                                    data-advance-id="{{ $rep->cash_advance_id }}"
                                                    data-claims="{{ $rep->claims }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editReportModal"
                                                />
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.submit', $rep) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary py-1 fw-bold fs-11 text-uppercase">Submit</button>
                                                </form>
                                            @endif

                                            @if($rep->status === 'submitted')
                                                <button type="button" class="btn btn-sm btn-soft-success py-1 fw-bold fs-11 text-uppercase" data-bs-toggle="modal" data-bs-target="#approveReportModal_{{ $rep->id }}">Approve</button>
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.reject', $rep) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger py-1 fw-bold fs-11 text-uppercase">Reject</button>
                                                </form>

                                                <!-- Modal to Approve Expense Report with Custom Amount -->
                                                <div class="modal fade text-start" id="approveReportModal_{{ $rep->id }}" tabindex="-1" aria-labelledby="approveReportModalLabel_{{ $rep->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
                                                        <div class="modal-content text-dark animate__animated animate__fadeIn">
                                                            <form method="POST" action="{{ route('hrms.travel-expense.report.approve', $rep) }}">
                                                                @csrf
                                                                <div class="modal-header">
                                                                    <h6 class="modal-title fw-bold" id="approveReportModalLabel_{{ $rep->id }}"><i class="feather-check-circle text-success me-1"></i> Approve Expense Claim</h6>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body d-flex flex-column gap-3">
                                                                    <p class="fs-12 text-muted mb-0">Review and specify the approved total expense amount. You can partially approve a lower amount if any individual items are disallowed.</p>
                                                                    
                                                                    @php
                                                                        $modalFullAdvance = $rep->cashAdvance ? ($rep->cashAdvance->approved_amount ?? $rep->cashAdvance->amount) : 0;
                                                                    @endphp
                                                                    <div class="p-3 bg-light rounded border text-dark">
                                                                        <div class="row g-2 fs-12">
                                                                            <div class="col-6">
                                                                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Requested Total</span>
                                                                                <strong class="text-dark">{{ $currencySymbol }}{{ number_format($rep->total_amount, 2) }}</strong>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Total Cash Advance</span>
                                                                                <strong class="text-dark">{{ $currencySymbol }}{{ number_format($modalFullAdvance, 2) }}</strong>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <x-ui.odoo-form-ui type="input" inputType="number" label="Approved Total Expense" name="approved_amount" id="approve_amount_input_{{ $rep->id }}" value="{{ $rep->total_amount }}" step="0.01" min="1" :required="true" oninput="calculateApprovalNet({{ $rep->id }}, {{ $modalFullAdvance }})" />

                                                                    <x-ui.odoo-form-ui type="select" label="Payout Channel" name="payout_channel" id="payout_channel_{{ $rep->id }}" :required="true" onchange="togglePayoutChannelWarning({{ $rep->id }})">
                                                                        <option value="accounting" selected>Accounting (Direct Payment)</option>
                                                                        <option value="payroll">Payroll (Adjust with Salary)</option>
                                                                    </x-ui.odoo-form-ui>

                                                                    <div id="payroll_warning_{{ $rep->id }}" class="alert alert-info border-info-subtle fs-11 py-2 px-2.5 mt-1 mb-0 d-none animate__animated animate__fadeIn">
                                                                        <i class="feather-info me-1"></i> Reimbursement will be automatically processed through the employee's salary slip in the next payroll cycle.
                                                                    </div>

                                                                    <div id="surplus_payroll_notice_{{ $rep->id }}" class="alert alert-warning border-warning-subtle fs-11 py-2 px-2.5 mt-1 mb-0 d-none animate__animated animate__fadeIn">
                                                                        <i class="feather-alert-triangle me-1"></i> <strong>Surplus Refund:</strong> Since the employee took a surplus advance, the balance will be recovered via salary deduction.
                                                                    </div>

                                                                    <div class="bg-light rounded border fs-12 d-flex flex-column text-dark mt-3" style="padding: 16px; gap: 10px;">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <span class="text-muted fw-medium">Calculated Reimbursement Payout:</span>
                                                                            <strong id="calc_payout_{{ $rep->id }}" class="text-primary fs-13">{{ $currencySymbol }}0.00</strong>
                                                                        </div>
                                                                        <div id="calc_surplus_wrapper_{{ $rep->id }}" class="d-flex justify-content-between align-items-center d-none text-warning fw-bold">
                                                                            <span>Refund due to Company:</span>
                                                                            <span id="calc_surplus_{{ $rep->id }}" class="fs-13">{{ $currencySymbol }}0.00</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-light border btn-sm text-uppercase fw-bold fs-11" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-success btn-sm text-uppercase fw-bold fs-11">Approve</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($rep->status === 'approved')
                                                @if(($rep->payout_channel ?? 'accounting') !== 'payroll')
                                                    <form method="POST" action="{{ route('hrms.travel-expense.report.pay', $rep) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success py-1 fw-bold fs-11"><i class="feather-credit-card"></i> Pay Out</button>
                                                    </form>
                                                @endif
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
                @if($expenseReports->hasPages())
                    <div class="mt-4 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted fs-12">
                            Showing {{ $expenseReports->firstItem() ?? 0 }} to {{ $expenseReports->lastItem() ?? 0 }} of {{ $expenseReports->total() }} records
                        </div>
                        <div class="d-flex justify-content-end">
                            {{ $expenseReports->links() }}
                        </div>
                    </div>
                @endif
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
                            <div class="alert alert-danger fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>Your current user session is not linked to an Employee profile. You cannot file requests on this page.</div>
                        @else
                            <x-ui.odoo-form-ui type="input" label="Purpose" name="purpose" placeholder="e.g. Sales pitch to prospective client" :required="true" />
                            <x-ui.odoo-form-ui type="input" label="Destination" name="destination" placeholder="e.g. Chicago office" :required="true" />
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" :required="true" />
                            <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" :required="true" />
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Estimated Budget ({{ $currencySymbol }})" name="estimated_budget" placeholder="0.00" step="0.01" min="0" :required="true" />

                            <!-- Integrated Cash Advance Option -->
                            <div class="border-top pt-3 mt-1">
                                <div class="form-check mb-2 d-flex align-items-center gap-2 ps-0">
                                    <input class="form-check-input mt-0 ms-0" type="checkbox" id="request_advance_toggle" name="request_advance" value="1" onchange="toggleTravelAdvanceAmount(this)" style="float: none;">
                                    <label class="form-check-label fw-bold fs-12 text-dark mb-0" for="request_advance_toggle">Request Cash Advance for this trip</label>
                                </div>
                                <div id="travel_advance_amount_wrapper" class="d-none mt-3">
                                    <x-ui.odoo-form-ui type="input" inputType="number" label="Advance Amount" name="advance_amount" id="travel_advance_amount_input" placeholder="0.00" step="0.01" min="1" />
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Cancel</button>
                        @if($employee)
                            <button type="submit" class="btn btn-primary text-uppercase fw-bold fs-12">Submit Request</button>
                        @endif
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
                            <div class="alert alert-danger fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>Your current user session is not linked to an Employee profile. You cannot file requests on this page.</div>
                        @else
                            <x-ui.odoo-form-ui type="select" label="Travel Request" name="travel_request_id" select2-selector="default">
                                <option value="">Standalone / Business Expense</option>
                                @foreach($myApprovedTravelRequests as $tr)
                                    <option value="{{ $tr->id }}">{{ $tr->purpose }} (to {{ $tr->destination }}) - Budget: {{ $currencySymbol }}{{ number_format($tr->approved_budget ?? $tr->estimated_budget, 2) }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" inputType="number" label="Amount" name="amount" placeholder="0.00" step="0.01" min="1" :required="true" />
                            <x-ui.odoo-form-ui type="input" label="Purpose" name="purpose" placeholder="e.g. Client entertainment & dinner costs" :required="true" />
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Cancel</button>
                        @if($employee)
                            <button type="submit" class="btn btn-primary text-uppercase fw-bold fs-12">Request Advance</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: FILE EXPENSE REPORT --}}
    <div class="modal fade" id="addReportModal" tabindex="-1" aria-labelledby="addReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content text-dark">
                <form method="POST" action="{{ route('hrms.travel-expense.report.store') }}" enctype="multipart/form-data"
                    id="addReportForm"
                    data-policy-url="{{ $employee ? route('hrms.travel-expense.employee-policy', $employee->id) : '' }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addReportModalLabel">File Expense Claim Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <div class="alert alert-danger fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>Your current user session is not linked to an Employee profile. You cannot file requests on this page.</div>
                        @else
                            <x-ui.odoo-form-ui type="input" label="Report Title" name="title" placeholder="e.g. Sales Event Claims Aug 2026" :required="true" />
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Travel Trip</label>
                                    <x-ui.odoo-form-ui type="select" name="travel_request_id" id="report_travel_request" select2-selector="default">
                                        <option value="">None</option>
                                        @foreach($myApprovedTravelRequests as $tr)
                                            <option value="{{ $tr->id }}">{{ $tr->destination }} ({{ $tr->purpose }})</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Cash Advance</label>
                                    <x-ui.odoo-form-ui type="select" name="cash_advance_id" id="report_cash_advance" select2-selector="default">
                                        <option value="">None</option>
                                        @foreach($myOpenCashAdvances as $ca)
                                            <option value="{{ $ca->id }}">${{ number_format($ca->approved_amount ?? $ca->amount, 2) }} - {{ $ca->purpose }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>

                            {{-- Claims items array dynamically added --}}
                            <div class="border-top pt-3 mt-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold mb-0" style="font-size: 13px;">Expense Claim Items *</h6>
                                    <button type="button" class="btn btn-sm btn-soft-primary px-2.5 py-1 text-uppercase fw-bold fs-11" id="btnAddClaimLine">
                                        <i class="feather-plus"></i> Add Row
                                    </button>
                                </div>

                                <div class="table-responsive overflow-hidden">
                                    <table class="odoo-table" id="claimsLinesTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 22%;">Category</th>
                                                <th style="width: 15%;">Date</th>
                                                <th style="width: 15%;">Amount</th>
                                                <th style="width: 25%;">Merchant / Details</th>
                                                <th style="width: 15%;">Receipt File</th>
                                                <th style="width: 8%;" class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="claimsLinesContainer">
                                            <tr class="claim-line-row">
                                                <td>
                                                    <select name="claims[0][category_id]" class="odoo-table-select text-dark" required onchange="validateLinePolicy(this)">
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <!-- Client-side policy limit warning inline badge -->
                                                    <div class="policy-limit-badge text-danger fw-semibold mt-1 fs-11 d-none" style="letter-spacing: 0.2px;"><i class="feather-alert-triangle"></i> Exceeds limit</div>
                                                </td>
                                                <td>
                                                    <input type="date" name="claims[0][date]" class="odoo-table-input text-dark text-center" value="{{ date('Y-m-d') }}" required>
                                                </td>
                                                <td>
                                                    <input type="number" name="claims[0][amount]" class="odoo-table-input text-dark text-end claim-amount-input" placeholder="0.00" step="0.01" min="0.01" required oninput="validateLinePolicy(this); updateReportTotals()">
                                                </td>
                                                <td>
                                                    <input type="text" name="claims[0][merchant]" class="odoo-table-input text-dark" placeholder="e.g. Uber, Hilton Hotel">
                                                </td>
                                                <td>
                                                    <label class="odoo-table-file-label mb-0" style="display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 6px 12px !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; justify-content: center !important;">
                                                        <i class="feather-upload-cloud" style="font-size: 13px !important; color: #64748b !important;"></i>
                                                        <span class="file-label-text text-muted">Attach Receipt</span>
                                                        <input type="file" name="claims[0][receipt]" class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                                                    </label>
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

                            {{-- Policy Violations Banner (shown on submit if exceeded) --}}
                            <div id="addReportPolicyWarning" class="d-none">
                                <div class="alert alert-warning border border-warning-subtle fs-12 mb-0 d-flex gap-2 align-items-start">
                                    <i class="feather-alert-triangle text-warning mt-0.5 fs-14"></i>
                                    <div>
                                        <div class="fw-bold text-dark mb-1">Policy Limit Exceeded</div>
                                        <ul id="addReportPolicyViolationList" class="mb-0 ps-3 text-dark"></ul>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Cancel</button>
                        @if($employee)
                            <button type="submit" class="btn btn-primary text-uppercase fw-bold fs-12">Save Draft</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: EDIT EXPENSE REPORT --}}
    <div class="modal fade" id="editReportModal" tabindex="-1" aria-labelledby="editReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content text-dark">
                <form method="POST" action="" enctype="multipart/form-data"
                    id="editReportForm"
                    data-policy-url="{{ $employee ? route('hrms.travel-expense.employee-policy', $employee->id) : '' }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="editReportModalLabel">Edit Expense Claim Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <div class="alert alert-danger fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>Your current user session is not linked to an Employee profile. You cannot file requests on this page.</div>
                        @else
                            <x-ui.odoo-form-ui type="input" label="Report Title" name="title" id="edit_report_title" placeholder="e.g. Sales Event Claims Aug 2026" :required="true" />
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Travel Trip</label>
                                    <x-ui.odoo-form-ui type="select" name="travel_request_id" id="edit_report_travel_request" select2-selector="default">
                                        <option value="">None</option>
                                        @foreach($myApprovedTravelRequests as $tr)
                                            <option value="{{ $tr->id }}">{{ $tr->destination }} ({{ $tr->purpose }})</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Cash Advance</label>
                                    <x-ui.odoo-form-ui type="select" name="cash_advance_id" id="edit_report_cash_advance" select2-selector="default">
                                        <option value="">None</option>
                                        @foreach($myOpenCashAdvances as $ca)
                                            <option value="{{ $ca->id }}">${{ number_format($ca->approved_amount ?? $ca->amount, 2) }} - {{ $ca->purpose }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>

                            {{-- Claims items array dynamically added --}}
                            <div class="border-top pt-3 mt-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold mb-0" style="font-size: 13px;">Expense Claim Items *</h6>
                                    <button type="button" class="btn btn-sm btn-soft-primary px-2.5 py-1 text-uppercase fw-bold fs-11" id="btnEditAddClaimLine">
                                        <i class="feather-plus"></i> Add Row
                                    </button>
                                </div>

                                <div class="table-responsive overflow-hidden">
                                    <table class="odoo-table" id="editClaimsLinesTable">
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
                                        <tbody id="editClaimsLinesContainer">
                                            <!-- Dynamic Edit Rows Populated by Javascript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Policy Violations Banner (shown on submit if exceeded) --}}
                            <div id="editReportPolicyWarning" class="d-none">
                                <div class="alert alert-warning border border-warning-subtle fs-12 mb-0 d-flex gap-2 align-items-start">
                                    <i class="feather-alert-triangle text-warning mt-0.5 fs-14"></i>
                                    <div>
                                        <div class="fw-bold text-dark mb-1">Policy Limit Exceeded</div>
                                        <ul id="editReportPolicyViolationList" class="mb-0 ps-3 text-dark"></ul>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Cancel</button>
                        @if($employee)
                            <button type="submit" class="btn btn-primary text-uppercase fw-bold fs-12">Update Report</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: VIEW EXPENSE REPORT DETAILS --}}
    <div class="modal fade text-start text-dark" id="viewReportModal" tabindex="-1" aria-labelledby="viewReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="viewReportModalLabel"><i class="feather-file-text text-primary me-1"></i> Expense Claim Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column gap-3">
                    <div class="p-3 bg-light rounded border text-dark">
                        <div class="row g-3 fs-13">
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Report Title</span>
                                <strong id="view_report_title" class="text-dark fs-14"></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Employee</span>
                                <strong id="view_report_employee" class="text-dark"></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Travel Trip</span>
                                <span id="view_report_travel" class="text-dark fw-semibold"></span>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Cash Advance</span>
                                <span id="view_report_advance" class="text-dark fw-semibold"></span>
                            </div>
                            <div class="col-md-6">
                                 <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Status</span>
                                 <span id="view_report_status" class="badge"></span>
                             </div>
                             <div class="col-md-6" id="view_report_channel_wrapper">
                                 <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Payout Channel</span>
                                 <span id="view_report_channel" class="badge"></span>
                             </div>
                        </div>
                    </div>

                    <div class="p-3 bg-soft-primary rounded border border-primary-subtle text-dark">
                        <div class="row text-center g-3 fs-13">
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Total Expenses</span>
                                <strong id="view_report_total" class="text-dark fs-15"></strong>
                            </div>
                            <div class="col-md-4 border-start border-end border-primary-subtle">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Advance Adjusted</span>
                                <strong id="view_report_adjusted" class="text-dark fs-15"></strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">Net Reimbursement</span>
                                <strong id="view_report_net" class="text-primary fs-16"></strong>
                            </div>
                        </div>
                    </div>

                    <div id="view_report_surplus_wrapper" class="p-3 bg-soft-warning rounded border border-warning-subtle text-dark d-none">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-alert-triangle text-warning fs-18"></i>
                            <div>
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-0.5">Returnable Surplus (Refund due to Company)</span>
                                <strong id="view_report_surplus" class="text-warning fs-15"></strong>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold mb-3 fs-13"><i class="feather-list me-1 text-primary"></i> Claim Items</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-dark fs-12" id="viewClaimsLinesTable" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Merchant</th>
                                        <th>Reason / Description</th>
                                        <th>Receipt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Dynamically populated rows --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        // =========================================================
        // EXPENSE POLICY VALIDATION ENGINE
        // Fetches employee policy once, validates claim rows on submit
        // Shows inline per-row warnings + blocks form if violations exist
        // =========================================================
        var expensePolicyCache = null;

        function fetchEmployeePolicy(policyUrl, callback) {
            if (expensePolicyCache !== null) {
                callback(expensePolicyCache);
                return;
            }
            if (!policyUrl) {
                callback({ rules: {} });
                return;
            }
            fetch(policyUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    expensePolicyCache = data;
                    callback(data);
                })
                .catch(() => callback({ rules: {} }));
        }

        function clearRowPolicyWarnings(tableSelector) {
            document.querySelectorAll(tableSelector + ' .policy-warning-badge').forEach(el => el.remove());
            document.querySelectorAll(tableSelector + ' .policy-warning-input').forEach(el => {
                el.classList.remove('policy-warning-input');
                el.style.borderBottomColor = '';
            });
        }

        function validateClaimRowsAgainstPolicy(tableSelector, policyRules, warningBannerEl, violationListEl) {
            clearRowPolicyWarnings(tableSelector);
            var violations = [];
            var currSym = '{{ $currencySymbol }}';

            document.querySelectorAll(tableSelector + ' tbody tr').forEach(function(row, idx) {
                var categorySelect = row.querySelector('select[name*="[category_id]"]');
                var amountInput   = row.querySelector('input[name*="[amount]"]');
                var receiptInput  = row.querySelector('input[type="file"][name*="[receipt]"]');
                var existingReceipt = row.querySelector('input[name*="[existing_receipt]"]');

                if (!categorySelect || !amountInput) return;

                var categoryId = categorySelect.value;
                var amount = parseFloat(amountInput.value) || 0;
                var rule = policyRules[categoryId];

                if (!rule) return;

                var rowViolations = [];

                // Check max_limit_per_claim
                if (rule.max_limit_per_claim && amount > rule.max_limit_per_claim) {
                    rowViolations.push('Amount ' + currSym + amount.toFixed(2) + ' exceeds the ' + rule.category_name + ' limit of ' + currSym + rule.max_limit_per_claim.toFixed(2));
                    amountInput.style.borderBottomColor = '#f59e0b';
                    amountInput.classList.add('policy-warning-input');
                }

                // Check receipt required
                var hasReceipt = (receiptInput && receiptInput.files && receiptInput.files.length > 0)
                                 || (existingReceipt && existingReceipt.value);
                var needsReceipt = rule.receipt_required
                    || (rule.receipt_required_threshold && amount > rule.receipt_required_threshold);
                if (needsReceipt && !hasReceipt) {
                    rowViolations.push('Receipt is required for ' + rule.category_name + ' claims' +
                        (rule.receipt_required_threshold ? ' above ' + currSym + rule.receipt_required_threshold.toFixed(2) : ''));
                }

                if (rowViolations.length > 0) {
                    // Show inline badge under the amount cell
                    var badge = document.createElement('div');
                    badge.className = 'policy-warning-badge text-warning fw-semibold mt-0.5';
                    badge.style.cssText = 'font-size: 10px; line-height: 1.2;';
                    badge.innerHTML = '<i class="feather-alert-triangle" style="font-size: 10px;"></i> ' + rowViolations[0];
                    amountInput.parentNode.appendChild(badge);

                    rowViolations.forEach(msg => violations.push('Row ' + (idx + 1) + ': ' + msg));
                }
            });

            // Show or hide the banner
            if (violations.length > 0) {
                violationListEl.innerHTML = violations.map(v => '<li>' + v + '</li>').join('');
                warningBannerEl.classList.remove('d-none');
            } else {
                warningBannerEl.classList.add('d-none');
            }

            return violations.length === 0;
        }

        // Intercept Add Report form submit
        $(document).on('submit', '#addReportForm', function(e) {
            var form = this;
            var policyUrl = form.dataset.policyUrl;
            var warningBanner = document.getElementById('addReportPolicyWarning');
            var violationList = document.getElementById('addReportPolicyViolationList');

            fetchEmployeePolicy(policyUrl, function(data) {
                var valid = validateClaimRowsAgainstPolicy('#claimsLinesTable', data.rules || {}, warningBanner, violationList);
                if (valid) {
                    form.submit();
                } else {
                    warningBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            e.preventDefault();
        });

        // Intercept Edit Report form submit
        $(document).on('submit', '#editReportForm', function(e) {
            var form = this;
            var policyUrl = form.dataset.policyUrl;
            var warningBanner = document.getElementById('editReportPolicyWarning');
            var violationList = document.getElementById('editReportPolicyViolationList');

            fetchEmployeePolicy(policyUrl, function(data) {
                var valid = validateClaimRowsAgainstPolicy('#editClaimsLinesTable', data.rules || {}, warningBanner, violationList);
                if (valid) {
                    form.submit();
                } else {
                    warningBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            e.preventDefault();
        });

        // Clear warnings when amount or category changes
        $(document).on('change input', '#claimsLinesTable select[name*="[category_id]"], #claimsLinesTable input[name*="[amount]"]', function() {
            clearRowPolicyWarnings('#claimsLinesTable');
            document.getElementById('addReportPolicyWarning').classList.add('d-none');
        });
        $(document).on('change input', '#editClaimsLinesTable select[name*="[category_id]"], #editClaimsLinesTable input[name*="[amount]"]', function() {
            clearRowPolicyWarnings('#editClaimsLinesTable');
            document.getElementById('editReportPolicyWarning').classList.add('d-none');
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Append modals to body to bypass stacking context / backdrop blur freeze issues
            ['addTravelModal', 'addAdvanceModal', 'addReportModal', 'editReportModal', 'viewReportModal'].forEach(id => {
                const modal = document.getElementById(id);
                if (modal) document.body.appendChild(modal);
            });

            // Append all dynamic approval modals to body as well
            document.querySelectorAll('[id^="approveTravelModal_"], [id^="approveAdvanceModal_"], [id^="approveReportModal_"]').forEach(modal => {
                document.body.appendChild(modal);
            });

            var activeRequest = null;
            var searchTimeout = null;

            function refreshPanel(url) {
                if (activeRequest) {
                    activeRequest.abort();
                }
                var controller = new AbortController();
                activeRequest = controller;

                var panel = document.querySelector('.erp-single-panel');
                if (panel) panel.style.opacity = '0.7';

                fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Error reloading page.');
                    return response.text();
                })
                .then(function(html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    
                    const newUrlParams = new URLSearchParams(url.search);
                    const activeTabName = newUrlParams.get('tab') || 'travel';
                    
                    // Determine active tab body container to replace (identical to Org Structure tabs)
                    const targetTbodyId = {
                        'travel': 'travelTableBody',
                        'advance': 'advanceTableBody',
                        'report': 'reportTableBody'
                    }[activeTabName];

                    if (targetTbodyId) {
                        var newTbody = doc.getElementById(targetTbodyId);
                        var oldTbody = document.getElementById(targetTbodyId);
                        if (newTbody && oldTbody) {
                            oldTbody.innerHTML = newTbody.innerHTML;
                        }
                    } else {
                        // Fallback: replace whole panel if active tab is not mapped
                        var newPanel = doc.querySelector('.erp-single-panel');
                        var oldPanel = document.querySelector('.erp-single-panel');
                        if (newPanel && oldPanel) {
                            oldPanel.innerHTML = newPanel.innerHTML;
                        }
                    }
                    
                    // Push history state to keep search/sort params in browser URL
                    history.pushState(null, '', url.toString());
                })
                .catch(function(err) {
                    if (err.name !== 'AbortError') {
                        window.location.href = url.toString();
                    }
                })
                .finally(function() {
                    if (panel) panel.style.opacity = '1';
                });
            }

            // Real-time search debouncing for all three tabs
            $(document).on('input keyup search', 'input[name="travel_search"], input[name="advance_search"], input[name="report_search"]', function() {
                var form = this.closest('form');
                var url = new URL(form.action || window.location.href);
                var formData = new FormData(form);
                for (var [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshPanel(url);
                }, 300);
            });

            // Intercept all GET forms submission (Search & Filter applies)
            $(document).on('submit', 'form[action*="travel-expense"]', function(e) {
                var method = this.getAttribute('method') || 'GET';
                if (method.toUpperCase() === 'GET') {
                    e.preventDefault();
                    var url = new URL(this.action || window.location.href);
                    var formData = new FormData(this);
                    for (var [key, val] of formData.entries()) {
                        url.searchParams.set(key, val);
                    }
                    refreshPanel(url);
                    
                    // Close the Bootstrap filter dropdown automatically
                    var dropdownToggle = $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]');
                    if (dropdownToggle.length > 0) {
                        bootstrap.Dropdown.getOrCreateInstance(dropdownToggle[0]).hide();
                    }
                }
            });

            // Intercept filter Reset button link click to fetch via AJAX
            $(document).on('click', 'form[action*="travel-expense"] a.btn-light[href*="tab="]', function(e) {
                e.preventDefault();
                var url = new URL(this.href, window.location.origin);
                refreshPanel(url);
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
                refreshPanel(url);
            };

            // Safe Select2 initializer helper to prevent Bootstrap modal closing issue
            window.safeInitSelect2 = function(modalSelector) {
                if (window.$ && $.fn.select2) {
                    var $modal = $(modalSelector);
                    if (!$modal.length) return;
                    $modal.find('.odoo-select2, [data-select2-selector]').each(function() {
                        var $select = $(this);
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }
                        $select.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            dropdownParent: $modal
                        });
                    });
                }
            };

            // Initialize all dropdowns inside travel-expense modals
            safeInitSelect2('#addTravelModal');
            safeInitSelect2('#addAdvanceModal');
            safeInitSelect2('#addReportModal');
            safeInitSelect2('#editReportModal');

            // Dynamic claims row addition
            let lineIndex = 1;
            const btnAddLine = document.getElementById('btnAddClaimLine');
            const tableBody = document.querySelector('#claimsLinesTable tbody');

            if (btnAddLine) {
                btnAddLine.addEventListener('click', function () {
                    const rowTemplate = `
                        <tr class="claim-line-row">
                            <td>
                                <select name="claims[${lineIndex}][category_id]" class="odoo-table-select odoo-select2 text-dark" required>
                                    <option value="" disabled selected>-- Category --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="date" name="claims[${lineIndex}][date]" class="odoo-table-input text-dark text-center" required value="{{ date('Y-m-d') }}">
                            </td>
                            <td>
                                <input type="number" name="claims[${lineIndex}][amount]" step="0.01" min="0.01" class="odoo-table-input text-dark text-end claim-amount-input" placeholder="0.00" required>
                            </td>
                            <td>
                                <input type="text" name="claims[${lineIndex}][merchant]" class="odoo-table-input text-dark" placeholder="e.g. Uber, Hilton Hotel">
                            </td>
                            <td>
                                <label class="odoo-table-file-label mb-0" style="display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 6px 12px !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; justify-content: center !important;">
                                    <i class="feather-upload-cloud" style="font-size: 13px !important; color: #64748b !important;"></i>
                                    <span class="file-label-text text-muted">Attach Receipt</span>
                                    <input type="file" name="claims[${lineIndex}][receipt]" class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                                </label>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-line p-0" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', rowTemplate);

                    // Re-initialize select2 for the newly added row select box
                    if (window.$ && $.fn.select2) {
                        var $newRow = $(tableBody).find('tr').last();
                        $newRow.find('.odoo-select2').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            dropdownParent: $('#addReportModal')
                        });
                    }

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

            // Receipt file name display & state helper
            window.updateRowFileName = function(input) {
                var fileTextSpan = $(input).siblings('.file-label-text');
                if (input.files && input.files.length > 0) {
                    var filename = input.files[0].name;
                    if (filename.length > 15) {
                        filename = filename.substring(0, 12) + '...';
                    }
                    fileTextSpan.text(filename).css('color', '#10b981');
                    $(input).closest('.odoo-table-file-label').css({
                        'border-color': '#10b981',
                        'background-color': '#ecfdf5'
                    }).find('i').css('color', '#10b981');
                } else {
                    fileTextSpan.text('Attach Receipt').css('color', '#64748b');
                    $(input).closest('.odoo-table-file-label').css({
                        'border-color': '#cbd5e1',
                        'background-color': '#f8fafc'
                    }).find('i').css('color', '#64748b');
                }
            };

            // Toggle Cash Advance Amount input visibility and pre-populate budget value
            window.toggleTravelAdvanceAmount = function(checkbox) {
                var wrapper = document.getElementById('travel_advance_amount_wrapper');
                var input = document.getElementById('travel_advance_amount_input');
                if (checkbox.checked) {
                    wrapper.classList.remove('d-none');
                    if (input) {
                        input.setAttribute('required', 'required');
                        var budgetInput = document.querySelector('input[name="estimated_budget"]');
                        if (budgetInput && budgetInput.value && !input.value) {
                            input.value = budgetInput.value;
                        }
                    }
                } else {
                    wrapper.classList.add('d-none');
                    if (input) {
                        input.removeAttribute('required');
                        input.value = '';
                    }
                }
            };

            // Let's keep track of edit line indexes
            let editLineIndex = 0;

            // Populate Report Edit Modal
            $(document).on('click', '.btn-edit-report', function() {
                var id = this.getAttribute('data-id');
                var title = this.getAttribute('data-title');
                var travelId = this.getAttribute('data-travel-id');
                var advanceId = this.getAttribute('data-advance-id');
                var claims = JSON.parse(this.getAttribute('data-claims') || '[]');

                var form = document.querySelector('#editReportModal form');
                if (form) form.action = '{{ url('hrms/travel-expense/report') }}/' + id + '/update';

                document.getElementById('edit_report_title').value = title || '';
                $('#edit_report_travel_request').val(travelId || '').trigger('change.select2');
                $('#edit_report_cash_advance').val(advanceId || '').trigger('change.select2');

                var editTableBody = document.querySelector('#editClaimsLinesTable tbody');
                editTableBody.innerHTML = ''; // Clear previous rows

                editLineIndex = 0;

                claims.forEach(function(claim) {
                    var dateStr = claim.expense_date;
                    if (dateStr && dateStr.includes('T')) {
                        dateStr = dateStr.split('T')[0];
                    }
                    
                    var receiptLabelHtml = '';
                    if (claim.receipt_path) {
                        receiptLabelHtml = `
                            <div class="existing-receipt-indicator mb-1 text-center">
                                <span class="badge bg-soft-success text-success px-2 py-1 fs-10 rounded-pill"><i class="feather-check-circle me-1"></i>Attached</span>
                                <input type="hidden" name="claims[${editLineIndex}][existing_receipt]" value="${claim.receipt_path}">
                            </div>
                        `;
                    }

                    var rowHtml = `
                        <tr class="edit-claim-line-row">
                            <td>
                                <select name="claims[${editLineIndex}][category_id]" class="odoo-table-select odoo-select2" required>
                                    <option value="" disabled>-- Category --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" ${claim.expense_category_id == {{ $cat->id }} ? 'selected' : ''}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="date" name="claims[${editLineIndex}][date]" class="odoo-table-input" required value="${dateStr}">
                            </td>
                            <td>
                                <input type="number" name="claims[${editLineIndex}][amount]" step="0.01" min="0.01" class="odoo-table-input" placeholder="0.00" required value="${claim.amount}">
                            </td>
                            <td>
                                <input type="text" name="claims[${editLineIndex}][merchant]" class="odoo-table-input mb-1" placeholder="Merchant name" value="${claim.merchant || ''}">
                                <input type="text" name="claims[${editLineIndex}][desc]" class="odoo-table-input" placeholder="Reason / description" value="${claim.description || ''}">
                            </td>
                            <td>
                                ${receiptLabelHtml}
                                <label class="odoo-table-file-label mb-0">
                                    <i class="feather-upload-cloud"></i>
                                    <span class="file-label-text text-muted">${claim.receipt_path ? 'Replace File' : 'Attach Receipt'}</span>
                                    <input type="file" name="claims[${editLineIndex}][receipt]" class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                                </label>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-edit-line p-0" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    editTableBody.insertAdjacentHTML('beforeend', rowHtml);
                    editLineIndex++;
                });

                // Initialize select2 inside newly populated rows
                if (window.$ && $.fn.select2) {
                    $('#editReportModal .odoo-select2').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: $('#editReportModal')
                    });
                }
            });

            // Add row inside Edit modal
            $(document).on('click', '#btnEditAddClaimLine', function() {
                var editTableBody = document.querySelector('#editClaimsLinesTable tbody');
                var rowHtml = `
                    <tr class="edit-claim-line-row">
                        <td>
                            <select name="claims[${editLineIndex}][category_id]" class="odoo-table-select odoo-select2 text-dark" required>
                                <option value="" disabled selected>-- Category --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="date" name="claims[${editLineIndex}][date]" class="odoo-table-input text-dark text-center" required value="{{ date('Y-m-d') }}">
                        </td>
                        <td>
                            <input type="number" name="claims[${editLineIndex}][amount]" step="0.01" min="0.01" class="odoo-table-input text-dark text-end claim-amount-input" placeholder="0.00" required>
                        </td>
                        <td>
                            <input type="text" name="claims[${editLineIndex}][merchant]" class="odoo-table-input text-dark" placeholder="e.g. Uber, Hilton Hotel">
                        </td>
                        <td>
                            <label class="odoo-table-file-label mb-0" style="display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 6px 12px !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; justify-content: center !important;">
                                <i class="feather-upload-cloud" style="font-size: 13px !important; color: #64748b !important;"></i>
                                <span class="file-label-text text-muted">Attach Receipt</span>
                                <input type="file" name="claims[${editLineIndex}][receipt]" class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                            </label>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-edit-line p-0" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
                editTableBody.insertAdjacentHTML('beforeend', rowHtml);

                if (window.$ && $.fn.select2) {
                    var $newRow = $(editTableBody).find('tr').last();
                    $newRow.find('.odoo-select2').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: $('#editReportModal')
                    });
                }
                editLineIndex++;
            });

            // Remove row inside Edit modal
            $(document).on('click', '.btn-remove-edit-line', function() {
                var rows = document.querySelectorAll('.edit-claim-line-row');
                if (rows.length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('At least one claim line is required in an expense report.');
                }
            });

            // Populate Report View Modal
            $(document).on('click', '.btn-view-report', function() {
                var id = this.getAttribute('data-id');
                var title = this.getAttribute('data-title');
                var employee = this.getAttribute('data-employee');
                var travel = this.getAttribute('data-travel');
                var advance = this.getAttribute('data-advance');
                var total = this.getAttribute('data-total');
                var adjusted = this.getAttribute('data-adjusted');
                var net = this.getAttribute('data-net');
                var status = this.getAttribute('data-status');
                var claims = JSON.parse(this.getAttribute('data-claims') || '[]');
                var fullAdvance = parseFloat(this.getAttribute('data-full-advance')) || 0;
                var currentExpense = parseFloat(this.getAttribute('data-current-expense')) || 0;

                var payoutChannel = this.getAttribute('data-payout-channel') || 'accounting';
                
                document.getElementById('view_report_title').innerText = title || '';
                document.getElementById('view_report_employee').innerText = employee || '';
                document.getElementById('view_report_travel').innerText = travel || 'None';
                document.getElementById('view_report_advance').innerText = advance || 'None';
                var currSym = '{{ $currencySymbol }}';
                document.getElementById('view_report_total').innerText = total || (currSym + '0.00');
                document.getElementById('view_report_adjusted').innerText = adjusted || (currSym + '0.00');
                document.getElementById('view_report_net').innerText = net || (currSym + '0.00');

                // Display Refund warning if employee has surplus advance funds
                var surplusWrapper = document.getElementById('view_report_surplus_wrapper');
                var surplusValEl = document.getElementById('view_report_surplus');
                if (fullAdvance > currentExpense) {
                    var surplus = fullAdvance - currentExpense;
                    surplusValEl.innerText = currSym + surplus.toFixed(2);
                    surplusWrapper.classList.remove('d-none');
                } else {
                    surplusWrapper.classList.add('d-none');
                }

                // Render Payout Channel wrapper only if approved or paid
                var channelWrapper = document.getElementById('view_report_channel_wrapper');
                var channelEl = document.getElementById('view_report_channel');
                if (status.toLowerCase() === 'approved' || status.toLowerCase() === 'paid') {
                    channelWrapper.classList.remove('d-none');
                    channelEl.className = 'badge px-2 py-1 fs-10 ';
                    if (payoutChannel === 'payroll') {
                        channelEl.className += 'bg-soft-info text-info';
                        channelEl.innerText = 'Payroll';
                    } else {
                        channelEl.className += 'bg-soft-primary text-primary';
                        channelEl.innerText = 'Accounting';
                    }
                } else {
                    channelWrapper.classList.add('d-none');
                }

                // Render status badge
                var badgeEl = document.getElementById('view_report_status');
                badgeEl.className = 'badge px-2.5 py-1 fs-11 rounded-pill ';
                if (status.toLowerCase() === 'draft') {
                    badgeEl.className += 'bg-secondary text-white';
                } else if (status.toLowerCase() === 'submitted') {
                    badgeEl.className += 'bg-soft-warning text-warning';
                } else if (status.toLowerCase() === 'approved') {
                    badgeEl.className += 'bg-soft-success text-success';
                } else if (status.toLowerCase() === 'rejected') {
                    badgeEl.className += 'bg-soft-danger text-danger';
                } else {
                    badgeEl.className += 'bg-soft-primary text-primary';
                }
                badgeEl.innerText = status;

                // Render claims table rows
                var viewTableBody = document.querySelector('#viewClaimsLinesTable tbody');
                viewTableBody.innerHTML = '';

                claims.forEach(function(claim) {
                    var categoryName = claim.category ? claim.category.name : 'Unknown';
                    var dateStr = claim.expense_date;
                    if (dateStr && dateStr.includes('T')) {
                        dateStr = dateStr.split('T')[0];
                    }

                    var receiptHtml = '-';
                    if (claim.receipt_path) {
                        receiptHtml = `<a href="/storage/${claim.receipt_path}" target="_blank" class="btn btn-xs btn-soft-info py-0.5 px-2 fw-bold fs-10"><i class="feather-image me-1"></i>Receipt</a>`;
                    }

                    var rowHtml = `
                        <tr>
                            <td class="fw-semibold text-dark">${categoryName}</td>
                            <td>${dateStr}</td>
                            <td class="fw-bold text-dark">${currSym}${parseFloat(claim.amount).toFixed(2)}</td>
                            <td>${claim.merchant || '-'}</td>
                            <td>${claim.description || '-'}</td>
                            <td>${receiptHtml}</td>
                        </tr>
                    `;
                    viewTableBody.insertAdjacentHTML('beforeend', rowHtml);
                });
            });

            // Real-time Approval Net Payout/Refund Calculation
            window.calculateApprovalNet = function(id, advance) {
                var input = document.getElementById('approve_amount_input_' + id);
                if (!input) return;
                var approvedAmount = parseFloat(input.value) || 0;
                var payoutEl = document.getElementById('calc_payout_' + id);
                var surplusEl = document.getElementById('calc_surplus_' + id);
                var surplusWrapper = document.getElementById('calc_surplus_wrapper_' + id);
                var surplusNotice = document.getElementById('surplus_payroll_notice_' + id);
                var selectEl = document.getElementById('payout_channel_' + id);

                var payout = Math.max(approvedAmount - advance, 0);
                var currSym = '{{ $currencySymbol }}';
                payoutEl.innerText = currSym + payout.toFixed(2);

                if (approvedAmount < advance) {
                    var surplus = advance - approvedAmount;
                    surplusEl.innerText = currSym + surplus.toFixed(2);
                    surplusWrapper.classList.remove('d-none');
                    if (surplusNotice) surplusNotice.classList.remove('d-none');

                    // Auto-select payroll if employee owes refund (surplus exists)
                    if (selectEl && selectEl.value !== 'payroll') {
                        selectEl.value = 'payroll';
                    }
                } else {
                    surplusWrapper.classList.add('d-none');
                    if (surplusNotice) surplusNotice.classList.add('d-none');

                    // Default to accounting if net reimbursement is due
                    if (selectEl && selectEl.value !== 'accounting') {
                        selectEl.value = 'accounting';
                    }
                }

                // Dynamically refresh channel warning visibility
                togglePayoutChannelWarning(id);
            };

            window.togglePayoutChannelWarning = function(id) {
                var select = document.getElementById('payout_channel_' + id);
                var warning = document.getElementById('payroll_warning_' + id);
                var surplusWrapper = document.getElementById('calc_surplus_wrapper_' + id);
                if (select && warning) {
                    // Hide general payroll warning if there is a surplus refund notice visible instead
                    var hasSurplus = surplusWrapper && !surplusWrapper.classList.contains('d-none');
                    if (select.value === 'payroll' && !hasSurplus) {
                        warning.classList.remove('d-none');
                    } else {
                        warning.classList.add('d-none');
                    }
                }
            };

            // Trigger initial calculation when approval modal opens
            $(document).on('shown.bs.modal', '[id^="approveReportModal_"]', function () {
                var modalId = this.id;
                var id = modalId.replace('approveReportModal_', '');
                var input = document.getElementById('approve_amount_input_' + id);
                if (input) {
                    input.dispatchEvent(new Event('input'));
                }
                var select = document.getElementById('payout_channel_' + id);
                if (select) {
                    select.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
@endpush
