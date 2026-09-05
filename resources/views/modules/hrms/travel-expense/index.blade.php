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

                                        @php
                                            $activeReport = $req->expenseReports->where('status', '!=', 'rejected')->first();
                                        @endphp
                                        @if($activeReport)
                                            <div class="mt-1 fs-11 text-primary fw-bold" title="Expense Claim filed for this trip">
                                                <i class="feather-file-text me-0.5"></i>Claimed: {{ $currencySymbol }}{{ number_format($activeReport->approved_amount ?: $activeReport->total_amount, 2) }}
                                            </div>
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

                                        @php
                                            $linkedAdv = $req->cashAdvances->first();
                                        @endphp
                                        @if($linkedAdv)
                                            <div class="mt-1">
                                                @if($linkedAdv->status === 'pending')
                                                    <span class="badge bg-soft-warning text-dark px-1.5 py-0.5 fs-10" title="Cash Advance Requested"><i class="feather-dollar-sign me-0.5"></i>Advance: {{ $currencySymbol }}{{ number_format($linkedAdv->amount, 2) }}</span>
                                                @elseif($linkedAdv->status === 'approved' || $linkedAdv->status === 'disbursed')
                                                    <span class="badge bg-soft-info text-info px-1.5 py-0.5 fs-10" title="Cash Advance Approved"><i class="feather-check-circle me-0.5"></i>Advance Approved</span>
                                                @elseif($linkedAdv->status === 'rejected')
                                                    <span class="badge bg-soft-danger text-danger px-1.5 py-0.5 fs-10" title="Cash Advance Rejected"><i class="feather-x-circle me-0.5"></i>Advance Rejected</span>
                                                @endif
                                            </div>
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

                                            <!-- Modal to Approve Travel Request with Custom Budget & Linked Cash Advance -->
                                            <div class="modal fade text-start" id="approveTravelModal_{{ $req->id }}" tabindex="-1" aria-labelledby="approveTravelModalLabel_{{ $req->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                                                    <div class="modal-content text-dark">
                                                        <form method="POST" action="{{ route('hrms.travel-expense.travel.approve', $req) }}">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h6 class="modal-title fw-bold" id="approveTravelModalLabel_{{ $req->id }}"><i class="feather-check-circle text-success me-1"></i> Approve Travel Request</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body d-flex flex-column gap-3">
                                                                <p class="fs-12 text-muted mb-0">Specify the approved budget for this trip. You can also approve the requested cash advance at the same time.</p>
                                                                
                                                                <div class="p-3 bg-light rounded border">
                                                                    <div class="row g-2 fs-12">
                                                                        <div class="col-6">
                                                                            <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Requested Budget</span>
                                                                            <strong class="text-dark">{{ $currencySymbol }}{{ number_format($req->estimated_budget, 2) }}</strong>
                                                                        </div>
                                                                        @php
                                                                            $pendingAdv = $req->cashAdvances->where('status', 'pending')->first();
                                                                        @endphp
                                                                        @if($pendingAdv)
                                                                            <div class="col-6">
                                                                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Requested Advance</span>
                                                                                <strong class="text-primary">{{ $currencySymbol }}{{ number_format($pendingAdv->amount, 2) }}</strong>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>

                                                                <x-ui.odoo-form-ui type="input" inputType="number" label="Approved Budget" name="approved_budget" value="{{ $req->estimated_budget }}" step="0.01" min="0.01" :required="true" />

                                                                @if($pendingAdv)
                                                                    <div class="p-3 bg-soft-info rounded border border-info-subtle mt-1">
                                                                        <div class="form-check ps-0 d-flex align-items-center gap-2 mb-2">
                                                                            <input class="form-check-input mt-0 ms-0" type="checkbox" name="approve_cash_advance" value="1" id="approve_advance_cb_{{ $req->id }}" checked style="float: none;" onchange="document.getElementById('advance_amt_wrapper_{{ $req->id }}').classList.toggle('d-none', !this.checked)">
                                                                            <label class="form-check-label fw-bold fs-12 text-dark mb-0" for="approve_advance_cb_{{ $req->id }}">
                                                                                <i class="feather-dollar-sign text-info me-1"></i>Also Approve Linked Cash Advance
                                                                            </label>
                                                                        </div>
                                                                        <div id="advance_amt_wrapper_{{ $req->id }}">
                                                                            <x-ui.odoo-form-ui type="input" inputType="number" label="Approved Advance Amount" name="approved_advance_amount" value="{{ $pendingAdv->amount }}" step="0.01" min="0.01" />
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light border btn-sm text-uppercase fw-bold fs-11" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success btn-sm text-uppercase fw-bold fs-11">Approve</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif($req->status === 'approved')
                                            @php
                                                $activeUnpaidReport = $req->expenseReports->whereIn('status', ['draft', 'submitted', 'partially_approved', 'approved'])->first();
                                                $paidReport = $req->expenseReports->where('status', 'paid')->first();
                                                $approvedAdv = $req->cashAdvances->where('status', 'approved')->first();
                                            @endphp
                                            <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                                @if($approvedAdv)
                                                    <form method="POST" action="{{ route('hrms.travel-expense.advance.disburse', $approvedAdv) }}" class="m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success py-1 fw-bold fs-11 text-uppercase" title="Disburse Cash Advance ({{ $currencySymbol }}{{ number_format($approvedAdv->approved_amount ?? $approvedAdv->amount, 2) }})">
                                                            <i class="feather-credit-card me-1"></i>Disburse Advance
                                                        </button>
                                                    </form>
                                                @endif

                                                @if($activeUnpaidReport)
                                                    @if($activeUnpaidReport->status === 'draft')
                                                        <span class="badge bg-secondary text-white px-2 py-1 fs-11 rounded-pill"><i class="feather-file-text me-1"></i>Claim Drafted</span>
                                                    @elseif($activeUnpaidReport->status === 'submitted')
                                                        <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 rounded-pill"><i class="feather-clock me-1"></i>Claim Submitted</span>
                                                    @elseif($activeUnpaidReport->status === 'partially_approved')
                                                        <span class="badge bg-soft-info text-info px-2 py-1 fs-11 rounded-pill"><i class="feather-alert-circle me-1"></i>Partially Approved</span>
                                                    @elseif($activeUnpaidReport->status === 'approved')
                                                        <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill"><i class="feather-check-circle me-1"></i>Claim Approved</span>
                                                    @endif
                                                @elseif($paidReport)
                                                    <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill"><i class="feather-check-circle me-1"></i>Claim Paid</span>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-soft-primary py-1 fw-bold fs-11 claim-travel-expense-btn"
                                                            data-travel-id="{{ $req->id }}"
                                                            data-cash-advance-id="">
                                                        <i class="feather-plus me-1"></i>Supplementary Claim
                                                    </button>
                                                @else
                                                    <button type="button" 
                                                            class="btn btn-sm btn-soft-primary py-1 fw-bold fs-11 claim-travel-expense-btn"
                                                            data-travel-id="{{ $req->id }}"
                                                            data-cash-advance-id="{{ $req->cashAdvances->whereIn('status', ['approved', 'disbursed'])->first()?->id ?? '' }}">
                                                        <i class="feather-dollar-sign me-1"></i>Claim Expense
                                                    </button>
                                                @endif
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
                                                    @if($claim->receipt_paths)
                                                        @foreach($claim->receipt_paths as $k => $rPath)
                                                            <a href="{{ asset('storage/' . $rPath) }}" target="_blank" class="ms-1 text-info fs-10 fw-bold me-1"><i class="feather-image"></i> Receipt {{ count($claim->receipt_paths) > 1 ? ($k + 1) : '' }}</a>
                                                        @endforeach
                                                    @endif
                                                    @if(($claim->status ?? '') === 'rejected')
                                                        <div class="ms-2 mt-0.5 fs-11 text-danger fw-semibold">
                                                            <i class="feather-x-circle me-1"></i>Rejected: <span class="fw-normal text-danger">{{ $claim->rejection_reason ?? 'Reason not specified' }}</span>
                                                        </div>
                                                    @elseif(($claim->status ?? '') === 'approved' && $rep->status === 'partially_approved')
                                                        <div class="ms-2 mt-0.5 fs-10 text-success fw-semibold">
                                                            <i class="feather-check-circle me-1"></i>Approved: {{ $currencySymbol }}{{ number_format($claim->approved_amount ?? $claim->amount, 2) }}
                                                        </div>
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
                                        @elseif($rep->status === 'partially_approved')
                                            <span class="badge bg-soft-warning text-dark px-2 py-1 fs-11 rounded-pill"><i class="feather-alert-circle me-1"></i>Partially Approved</span>
                                        @elseif($rep->status === 'approved')
                                            <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill">Approved</span>
                                        @elseif($rep->status === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2 py-1 fs-11 rounded-pill">Rejected</span>
                                        @elseif($rep->status === 'paid')
                                            <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill">Paid</span>
                                        @endif

                                        @if($rep->status === 'approved' || $rep->status === 'paid' || $rep->status === 'partially_approved')
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
                                                data-status="{{ ucfirst(str_replace('_', ' ', $rep->status)) }}"
                                                data-payout-channel="{{ $rep->payout_channel ?? 'accounting' }}"
                                                data-claims="{{ $rep->claims->load('category') }}"
                                                data-full-advance="{{ $fullAdvance }}"
                                                data-current-expense="{{ $currentExpense }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewReportModal"
                                            />

                                            @php
                                                $isOwner = $employee && $rep->employee_id == $employee->id;
                                            @endphp

                                            {{-- EMPLOYEE / OWNER ACTIONS --}}
                                            @if(in_array($rep->status, ['draft', 'partially_approved']) || ($rep->status === 'rejected' && !$rep->travel_request_id))
                                                <x-ui.icon-btn type="button" variant="soft-primary" size="sm" class="btn-edit-report"
                                                    icon="feather-edit-3"
                                                    title="{{ in_array($rep->status, ['partially_approved', 'rejected']) ? 'Fix & Resubmit Claims' : 'Edit Report' }}"
                                                    data-id="{{ $rep->id }}"
                                                    data-title="{{ $rep->title }}"
                                                    data-status="{{ $rep->status }}"
                                                    data-travel-id="{{ $rep->travel_request_id }}"
                                                    data-advance-id="{{ $rep->cash_advance_id }}"
                                                    data-claims="{{ $rep->claims->load('category') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editReportModal"
                                                />
                                            @endif

                                            @if($rep->status === 'draft')
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.submit', $rep) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary py-1 fw-bold fs-11 text-uppercase">Submit</button>
                                                </form>
                                            @endif

                                            @php
                                                $hasRejectedItems = $rep->claims->where('status', 'rejected')->count() > 0;
                                                $showSupplementary = $rep->travel_request_id && (
                                                    $rep->status === 'rejected' ||
                                                    $rep->status === 'partially_approved' ||
                                                    ($rep->status === 'paid' && $hasRejectedItems)
                                                );
                                            @endphp

                                            @if($showSupplementary)
                                                <button type="button" 
                                                        class="btn btn-sm btn-soft-primary py-1 fw-bold fs-11 claim-supplementary-btn"
                                                        data-travel-id="{{ $rep->travel_request_id }}"
                                                        data-cash-advance-id=""
                                                        data-title="Supplementary Claim - {{ $rep->travelRequest->destination ?? $rep->title }}"
                                                        title="File a supplementary claim for additional expenses on this travel trip">
                                                    <i class="feather-plus me-1"></i>Supplementary
                                                </button>
                                            @endif

                                            {{-- ACTIONS --}}
                                            @if($rep->status === 'submitted')
                                                <button type="button" class="btn btn-sm btn-soft-success py-1 fw-bold fs-11 text-uppercase" data-bs-toggle="modal" data-bs-target="#approveReportModal_{{ $rep->id }}">Review & Approve</button>

                                                <form method="POST" action="{{ route('hrms.travel-expense.report.reject', $rep) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger py-1 fw-bold fs-11 text-uppercase">Reject</button>
                                                </form>

                                                <!-- Modal to Approve Expense Report with Custom Amount & Itemized Partial Approval -->
                                                <div class="modal fade text-start" id="approveReportModal_{{ $rep->id }}" tabindex="-1" aria-labelledby="approveReportModalLabel_{{ $rep->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <form method="POST" action="{{ route('hrms.travel-expense.report.approve', $rep) }}">
                                                                @csrf
                                                                <input type="hidden" name="approved_amount" id="approve_amount_input_{{ $rep->id }}" value="{{ $rep->total_amount }}">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title fw-bold" id="approveReportModalLabel_{{ $rep->id }}"><i class="feather-check-square text-success me-1"></i> Review & Approve Expense Report</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body d-flex flex-column gap-3 text-start">
                                                                    <div class="alert alert-info border-info-subtle fs-12 mb-0 d-flex gap-2 align-items-center">
                                                                        <i class="feather-info text-info fs-15"></i>
                                                                        <div>Select individual item decisions below (Approve/Reject). Total payout will recalculate automatically.</div>
                                                                    </div>
                                                                    <x-ui.odoo-form-ui type="table">
                                                                         <thead>
                                                                             <tr>
                                                                                 <th style="width: 30%;">Item / Category</th>
                                                                                 <th style="width: 15%;">Date</th>
                                                                                 <th style="width: 15%;">Claimed Amount</th>
                                                                                 <th style="width: 20%;">Decision</th>
                                                                                 <th style="width: 20%;">Rejection Reason</th>
                                                                             </tr>
                                                                         </thead>
                                                                         <tbody>
                                                                             @foreach($rep->claims as $cItem)
                                                                                 <tr>
                                                                                     <td>
                                                                                         <div class="fw-bold text-dark">{{ $cItem->category->name ?? 'Claim Item' }}</div>
                                                                                     </td>
                                                                                     <td>{{ $cItem->expense_date ? \Carbon\Carbon::parse($cItem->expense_date)->format('M d, Y') : '' }}</td>
                                                                                     <td class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($cItem->amount, 2) }}</td>
                                                                                     <td>
                                                                                          <select name="items[{{ $cItem->id }}][decision]" 
                                                                                                      id="item_decision_sel_{{ $cItem->id }}"
                                                                                                      class="odoo-table-select claim-item-decision-select fw-semibold" 
                                                                                                      data-claim-id="{{ $cItem->id }}"
                                                                                                      data-amount="{{ $cItem->amount }}"
                                                                                                      data-full-advance="{{ $fullAdvance }}"
                                                                                                      onchange="handleItemDecisionChange(this, {{ $rep->id }}, {{ $fullAdvance }})">
                                                                                              <option value="approved" @selected(($cItem->status ?? 'approved') !== 'rejected')>Approve</option>
                                                                                              <option value="rejected" @selected(($cItem->status ?? '') === 'rejected')>Reject</option>
                                                                                          </select>
                                                                                          <input type="hidden" name="items[{{ $cItem->id }}][approved_amount]" id="item_approved_amt_{{ $cItem->id }}" value="{{ (($cItem->status ?? '') === 'rejected') ? 0 : $cItem->amount }}">
                                                                                      </td>
                                                                                      <td>
                                                                                          <input type="text" 
                                                                                                 name="items[{{ $cItem->id }}][rejection_reason]" 
                                                                                                 id="item_rejection_reason_{{ $cItem->id }}" 
                                                                                                 class="odoo-table-input item-rejection-input {{ (($cItem->status ?? '') === 'rejected') ? '' : 'd-none' }}" 
                                                                                                 placeholder="Reason for rejection..." 
                                                                                                 value="{{ $cItem->rejection_reason ?? '' }}" />
                                                                                      </td>
                                                                                 </tr>
                                                                             @endforeach
                                                                         </tbody>
                                                                     </x-ui.odoo-form-ui>
                                                                    <div id="surplus_payroll_notice_{{ $rep->id }}" class="alert alert-warning border-warning-subtle fs-11 py-2 px-2.5 mt-1 mb-0 d-none animate__animated animate__fadeIn">
                                                                        <i class="feather-alert-triangle me-1"></i> <strong>Surplus Refund:</strong> Since the employee took a surplus advance, the balance will be recovered via salary deduction.
                                                                    </div>

                                                                    <div class="bg-light rounded border fs-12 d-flex flex-column text-dark mt-3" style="padding: 16px; gap: 10px;">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <span class="text-muted fw-medium">Calculated Approved Total & Payout:</span>
                                                                            <strong id="calc_payout_{{ $rep->id }}" class="text-primary fs-13">{{ $currencySymbol }}0.00</strong>
                                                                        </div>
                                                                        <div id="calc_surplus_wrapper_{{ $rep->id }}" class="d-flex justify-content-between align-items-center d-none text-warning fw-bold">
                                                                            <span>Refund due to Company:</span>
                                                                            <span id="calc_surplus_{{ $rep->id }}" class="fs-13">{{ $currencySymbol }}0.00</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer bg-light border-top">
                                                                    <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal" class="fw-bold text-uppercase">Cancel</x-ui.button>
                                                                    <x-ui.button type="submit" variant="success" icon="feather-check-circle" class="fw-bold text-uppercase">Approve Expense Report</x-ui.button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if(in_array($rep->status, ['approved', 'partially_approved']))
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.pay', $rep) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success py-1 fw-bold fs-11 text-uppercase" title="Pay Out Approved Amount ({{ $currencySymbol }}{{ number_format($rep->approved_amount ?? $rep->total_amount, 2) }})"><i class="feather-credit-card me-1"></i> Pay Out</button>
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
                            <x-ui.odoo-form-ui type="input" label="Purpose" name="purpose" placeholder="e.g. Sales pitch to prospective client" :required="true" :errorText="$errors->first('purpose')" :value="old('purpose')" />
                            <x-ui.odoo-form-ui type="input" label="Destination" name="destination" placeholder="e.g. Chicago office" :required="true" :errorText="$errors->first('destination')" :value="old('destination')" />
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" :required="true" :errorText="$errors->first('start_date')" :value="old('start_date')" />
                            <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" :required="true" :errorText="$errors->first('end_date')" :value="old('end_date')" />
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Estimated Budget ({{ $currencySymbol }})" name="estimated_budget" placeholder="0.00" step="0.01" min="0" :required="true" :errorText="$errors->first('estimated_budget')" :value="old('estimated_budget')" />

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

                            <x-ui.odoo-form-ui type="input" inputType="number" label="Amount" name="amount" placeholder="0.00" step="0.01" min="1" :required="true" :errorText="$errors->first('amount')" :value="old('amount')" />
                            <x-ui.odoo-form-ui type="input" label="Purpose" name="purpose" placeholder="e.g. Client entertainment & dinner costs" :required="true" :errorText="$errors->first('purpose')" :value="old('purpose')" />
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
                            <x-ui.odoo-form-ui type="input" label="Report Title" name="title" placeholder="e.g. Sales Event Claims Aug 2026" :required="true" :errorText="$errors->first('title')" :value="old('title')" />
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Travel Trip</label>
                                    <x-ui.odoo-form-ui type="select" name="travel_request_id" id="report_travel_request" select2-selector="default">
                                        <option value="">None</option>
                                        @foreach($myApprovedTravelRequests as $tr)
                                            @php
                                                $hasUnpaidClaim = $tr->expenseReports->whereIn('status', ['draft', 'submitted', 'partially_approved'])->isNotEmpty();
                                            @endphp
                                            <option value="{{ $tr->id }}" {{ $hasUnpaidClaim ? 'disabled' : '' }}>
                                                {{ $tr->destination }} ({{ $tr->purpose }}) {{ $hasUnpaidClaim ? ' - (Claim In Review)' : '' }}
                                            </option>
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
                                                <th style="width: 20%;">Category</th>
                                                <th style="width: 14%;">Date</th>
                                                <th style="width: 14%;">Amount</th>
                                                <th style="width: 26%;">Merchant / Details</th>
                                                <th style="width: 18%;">Receipt File</th>
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
                                                    <input type="text" name="claims[0][merchant]" class="odoo-table-input text-dark mb-1" placeholder="Merchant name (e.g. Uber, Hilton)">
                                                    <input type="text" name="claims[0][desc]" class="odoo-table-input text-dark" placeholder="Reason / notes (optional)">
                                                </td>
                                                <td>
                                                    <label class="odoo-table-file-label mb-0" style="display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 6px 12px !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; justify-content: center !important;">
                                                        <i class="feather-upload-cloud" style="font-size: 13px !important; color: #64748b !important;"></i>
                                                        <span class="file-label-text text-muted">Attach Receipts</span>
                                                        <input type="file" name="claims[0][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
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
                                                <th style="width: 20%;">Category</th>
                                                <th style="width: 14%;">Date</th>
                                                <th style="width: 14%;">Amount</th>
                                                <th style="width: 26%;">Merchant / Details</th>
                                                <th style="width: 18%;">Receipt File</th>
                                                <th style="width: 8%;" class="text-center">Action</th>
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
                                        <th>Status</th>
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

        function validateFormRequiredFields(form) {
            let hasErrors = false;
            let firstErrEl = null;

            // Clear previous dynamic errors inside this form
            form.querySelectorAll('.dynamic-error-feedback').forEach(el => el.remove());
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            let requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
            requiredFields.forEach(field => {
                if (field.disabled || field.readOnly || field.type === 'hidden') return;

                let val = field.value;
                let isEmpty = false;

                if (field.type === 'checkbox') {
                    isEmpty = !field.checked;
                } else if (field.type === 'file') {
                    let existingReceipt = field.closest('tr')?.querySelector('input[name*="[existing_receipt]"]');
                    isEmpty = (!field.files || field.files.length === 0) && (!existingReceipt || !existingReceipt.value);
                } else {
                    isEmpty = !val || !val.trim();
                }

                if (isEmpty) {
                    hasErrors = true;
                    field.classList.add('is-invalid');

                    let errorContainer = field.parentElement;
                    if (field.tagName === 'SELECT' && $(field).data('select2')) {
                        let s2Container = field.nextElementSibling;
                        if (s2Container && s2Container.classList.contains('select2-container')) {
                            s2Container.querySelector('.select2-selection')?.classList.add('is-invalid');
                            errorContainer = s2Container.parentElement;
                        }
                    } else if (field.classList.contains('odoo-table-file-input')) {
                        let fileLabel = field.closest('.odoo-table-file-label');
                        if (fileLabel) fileLabel.classList.add('is-invalid', 'border-danger', 'text-danger');
                    }

                    let labelName = '';
                    let odooFormGroup = field.closest('.odoo-form-group');
                    if (odooFormGroup) {
                        let labelEl = odooFormGroup.querySelector('.odoo-form-label');
                        if (labelEl) labelName = labelEl.textContent.replace('*', '').trim();
                    }
                    if (!labelName) {
                        let td = field.closest('td');
                        let tr = field.closest('tr');
                        let table = field.closest('table');
                        if (td && tr && table) {
                            let colIndex = Array.from(tr.children).indexOf(td);
                            let th = table.querySelector(`thead tr th:nth-child(${colIndex + 1})`);
                            if (th) labelName = th.textContent.trim();
                        }
                    }
                    if (!labelName) {
                        labelName = field.getAttribute('placeholder') || field.getAttribute('name') || 'This field';
                    }

                    let errorEl = document.createElement('div');
                    errorEl.className = 'invalid-feedback dynamic-error-feedback d-block text-danger fs-11 mt-1 fw-semibold';
                    errorEl.innerHTML = `<i class="feather-alert-circle me-1"></i>${labelName} is required.`;

                    if (field.tagName === 'SELECT' && $(field).data('select2')) {
                        let s2Container = field.nextElementSibling;
                        if (s2Container && s2Container.classList.contains('select2-container')) {
                            s2Container.parentNode.insertBefore(errorEl, s2Container.nextSibling);
                        } else {
                            field.parentNode.insertBefore(errorEl, field.nextSibling);
                        }
                    } else {
                        field.parentNode.insertBefore(errorEl, field.nextSibling);
                    }

                    if (!firstErrEl) firstErrEl = field;
                }
            });

            if (hasErrors && firstErrEl) {
                firstErrEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstErrEl.focus();
            }

            return !hasErrors;
        }

        // Intercept Add Report form submit
        $(document).on('submit', '#addReportForm', function(e) {
            var form = this;

            if (!validateFormRequiredFields(form)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

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

            if (!validateFormRequiredFields(form)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

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
                                <input type="text" name="claims[${lineIndex}][merchant]" class="odoo-table-input text-dark mb-1" placeholder="Merchant name (e.g. Uber, Hilton)">
                                <input type="text" name="claims[${lineIndex}][desc]" class="odoo-table-input text-dark" placeholder="Reason / notes (optional)">
                            </td>
                            <td>
                                <label class="odoo-table-file-label mb-0" style="display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 6px 12px !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; justify-content: center !important;">
                                    <i class="feather-upload-cloud" style="font-size: 13px !important; color: #64748b !important;"></i>
                                    <span class="file-label-text text-muted">Attach Receipts</span>
                                    <input type="file" name="claims[${lineIndex}][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
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

            // Accumulative multi-file store with DataTransfer
            window.rowFileStores = {};

            window.updateRowFileName = function(input) {
                if (!input) return;
                var $input = $(input);
                var rowId = input.getAttribute('data-row-id');
                if (!rowId) {
                    rowId = 'row_' + Math.random().toString(36).substring(2, 9);
                    input.setAttribute('data-row-id', rowId);
                }

                if (!window.rowFileStores[rowId]) {
                    window.rowFileStores[rowId] = [];
                }

                // Accumulate newly picked files into rowFileStores
                if (input.files && input.files.length > 0) {
                    Array.from(input.files).forEach(function(f) {
                        var exists = window.rowFileStores[rowId].some(function(existing) {
                            return existing.name === f.name && existing.size === f.size;
                        });
                        if (!exists) {
                            window.rowFileStores[rowId].push(f);
                        }
                    });
                }

                // Sync accumulated files back to input element using DataTransfer
                var dt = new DataTransfer();
                window.rowFileStores[rowId].forEach(function(f) {
                    dt.items.add(f);
                });
                try { input.files = dt.files; } catch(e) {}

                // Render chips container inside <td>
                var $td = $input.closest('td');
                var $chipsContainer = $td.find('.new-files-container');
                if ($chipsContainer.length === 0) {
                    $td.prepend('<div class="new-files-container d-flex flex-wrap gap-1 mb-1 justify-content-center"></div>');
                    $chipsContainer = $td.find('.new-files-container');
                }
                $chipsContainer.empty();

                window.rowFileStores[rowId].forEach(function(file, idx) {
                    var nameStr = file.name.length > 15 ? file.name.substring(0, 12) + '...' : file.name;
                    var chipHtml = `
                        <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-1 fs-10 rounded-pill d-inline-flex align-items-center me-1 mb-1 new-file-chip">
                            <i class="feather-paperclip me-1"></i>${nameStr}
                            <i class="feather-x ms-1 text-danger cursor-pointer" style="font-size: 11px;" title="Remove file" onclick="removeNewSelectedFile('${rowId}', ${idx}, this)"></i>
                        </span>
                    `;
                    $chipsContainer.append(chipHtml);
                });

                var fileTextSpan = $input.siblings('.file-label-text');
                var labelContainer = $input.closest('.odoo-table-file-label');
                var totalNewCount = window.rowFileStores[rowId].length;
                var hasExistingChips = $td.find('.existing-receipt-chip').length > 0;

                if (totalNewCount > 0 || hasExistingChips) {
                    fileTextSpan.text('+ Add More Files').css('color', '#0284c7');
                    labelContainer.css({
                        'border-color': '#0284c7',
                        'background-color': '#f0f9ff'
                    }).find('i').css('color', '#0284c7');
                } else {
                    fileTextSpan.text('Attach Receipts').css('color', '#64748b');
                    labelContainer.css({
                        'border-color': '#cbd5e1',
                        'background-color': '#f8fafc'
                    }).find('i').css('color', '#64748b');
                }
            };

            window.removeNewSelectedFile = function(rowId, fileIdx, btn) {
                if (window.rowFileStores[rowId]) {
                    window.rowFileStores[rowId].splice(fileIdx, 1);
                    var $td = $(btn).closest('td');
                    var input = $td.find('.odoo-table-file-input')[0];
                    if (input) {
                        var dt = new DataTransfer();
                        window.rowFileStores[rowId].forEach(function(f) {
                            dt.items.add(f);
                        });
                        try { input.files = dt.files; } catch(e) {}
                        input.value = '';
                        updateRowFileName(input);
                    }
                }
            };

            window.removeExistingReceipt = function(btn) {
                var $td = $(btn).closest('td');
                $(btn).closest('.existing-receipt-chip').remove();
                var input = $td.find('.odoo-table-file-input')[0];
                if (input) {
                    updateRowFileName(input);
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
                    let rPaths = [];
                    if (claim.receipt_path) {
                        if (typeof claim.receipt_path === 'string' && claim.receipt_path.startsWith('[')) {
                            try { rPaths = JSON.parse(claim.receipt_path); } catch(e) { rPaths = [claim.receipt_path]; }
                        } else if (Array.isArray(claim.receipt_path)) {
                            rPaths = claim.receipt_path;
                        } else {
                            rPaths = [claim.receipt_path];
                        }
                    }
                    if (rPaths.length > 0) {
                        receiptLabelHtml = `<div class="existing-receipts-container mb-1 d-flex flex-wrap gap-1 align-items-center justify-content-center">`;
                        rPaths.forEach(function(p, pIdx) {
                            receiptLabelHtml += `
                                <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-1 fs-10 rounded-pill d-inline-flex align-items-center me-1 mb-1 existing-receipt-chip">
                                    <a href="/storage/${p}" target="_blank" class="text-success text-decoration-none me-1"><i class="feather-paperclip me-1"></i>File ${rPaths.length > 1 ? (pIdx + 1) : ''}</a>
                                    <i class="feather-x cursor-pointer text-danger ms-1" style="font-size: 11px;" title="Remove file" onclick="removeExistingReceipt(this)"></i>
                                    <input type="hidden" name="claims[${editLineIndex}][existing_receipts][]" value="${p}">
                                </span>
                            `;
                        });
                        receiptLabelHtml += `</div>`;
                    }
                    var rejectionAlertHtml = '';
                    if ((claim.status || '').toLowerCase() === 'rejected') {
                        rejectionAlertHtml = `
                            <div class="alert alert-danger py-1 px-2 mb-1 fs-11 rounded" style="border-left: 3px solid #dc3545;">
                                <i class="feather-alert-triangle me-1"></i><strong>Rejected by HR:</strong> ${claim.rejection_reason || 'Please correct details or amount.'}
                            </div>
                        `;
                    }

                    var rowHtml = `
                        <tr class="edit-claim-line-row">
                            <td>
                                <select name="claims[${editLineIndex}][category_id]" class="odoo-table-select odoo-select2 text-dark" required>
                                    <option value="" disabled>-- Category --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" ${claim.expense_category_id == {{ $cat->id }} ? 'selected' : ''}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="date" name="claims[${editLineIndex}][date]" class="odoo-table-input text-dark text-center" required value="${dateStr}">
                            </td>
                            <td>
                                <input type="number" name="claims[${editLineIndex}][amount]" step="0.01" min="0.01" class="odoo-table-input text-dark text-end claim-amount-input" placeholder="0.00" required value="${claim.amount}">
                            </td>
                            <td>
                                ${rejectionAlertHtml}
                                <input type="text" name="claims[${editLineIndex}][merchant]" class="odoo-table-input text-dark mb-1" placeholder="Merchant name (e.g. Uber, Hilton)" value="${claim.merchant || ''}">
                                <input type="text" name="claims[${editLineIndex}][desc]" class="odoo-table-input text-dark" placeholder="Reason / notes (optional)" value="${claim.description || ''}">
                            </td>
                            <td>
                                ${receiptLabelHtml}
                                <label class="odoo-table-file-label mb-0" style="display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 6px 12px !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; justify-content: center !important;">
                                    <i class="feather-upload-cloud" style="font-size: 13px !important; color: #64748b !important;"></i>
                                    <span class="file-label-text text-muted">${rPaths.length > 0 ? '+ Add Files' : 'Attach Receipts'}</span>
                                    <input type="file" name="claims[${editLineIndex}][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
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
                            <input type="text" name="claims[${editLineIndex}][merchant]" class="odoo-table-input text-dark mb-1" placeholder="Merchant name (e.g. Uber, Hilton)">
                            <input type="text" name="claims[${editLineIndex}][desc]" class="odoo-table-input text-dark" placeholder="Reason / notes (optional)">
                        </td>
                        <td>
                            <label class="odoo-table-file-label mb-0" style="display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 6px 12px !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; justify-content: center !important;">
                                <i class="feather-upload-cloud" style="font-size: 13px !important; color: #64748b !important;"></i>
                                <span class="file-label-text text-muted">Attach Receipts</span>
                                <input type="file" name="claims[${editLineIndex}][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
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
                if (status.toLowerCase() === 'approved' || status.toLowerCase() === 'partially_approved' || status.toLowerCase() === 'paid') {
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
                } else if (status.toLowerCase() === 'partially_approved') {
                    badgeEl.className += 'bg-soft-info text-info';
                } else if (status.toLowerCase() === 'rejected') {
                    badgeEl.className += 'bg-soft-danger text-danger';
                } else {
                    badgeEl.className += 'bg-soft-primary text-primary';
                }
                badgeEl.innerText = status.replace('_', ' ');

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
                        let rPaths = [];
                        if (typeof claim.receipt_path === 'string' && claim.receipt_path.startsWith('[')) {
                            try { rPaths = JSON.parse(claim.receipt_path); } catch(e) { rPaths = [claim.receipt_path]; }
                        } else if (Array.isArray(claim.receipt_path)) {
                            rPaths = claim.receipt_path;
                        } else {
                            rPaths = [claim.receipt_path];
                        }
                        if (rPaths.length > 0) {
                            receiptHtml = rPaths.map((p, idx) => `<a href="/storage/${p}" target="_blank" class="btn btn-xs btn-soft-info py-0.5 px-2 fw-bold fs-10 me-1 mb-1"><i class="feather-image me-1"></i>Receipt ${rPaths.length > 1 ? (idx + 1) : ''}</a>`).join('');
                        }
                    }

                    var itemStatusHtml = '';
                    var itemStatusStr = (claim.status || 'pending').toLowerCase();
                    if (itemStatusStr === 'approved') {
                        var appAmt = (claim.approved_amount !== null && claim.approved_amount !== undefined) ? parseFloat(claim.approved_amount) : parseFloat(claim.amount);
                        itemStatusHtml = `<span class="badge bg-soft-success text-success px-2 py-1 fs-10"><i class="feather-check-circle me-1"></i>Approved (${currSym}${appAmt.toFixed(2)})</span>`;
                    } else if (itemStatusStr === 'rejected') {
                        itemStatusHtml = `<span class="badge bg-soft-danger text-danger px-2 py-1 fs-10" title="${claim.rejection_reason || ''}"><i class="feather-x-circle me-1"></i>Rejected</span>`;
                        if (claim.rejection_reason) {
                            itemStatusHtml += `<br><small class="text-danger fs-11" style="word-break: break-word;">${claim.rejection_reason}</small>`;
                        }
                    } else {
                        itemStatusHtml = `<span class="badge bg-soft-warning text-warning px-2 py-1 fs-10"><i class="feather-clock me-1"></i>Pending</span>`;
                    }

                    var rowHtml = `
                        <tr>
                            <td class="fw-semibold text-dark">${categoryName}</td>
                            <td>${dateStr}</td>
                            <td class="fw-bold text-dark">${currSym}${parseFloat(claim.amount).toFixed(2)}</td>
                            <td>${claim.merchant || '-'}</td>
                            <td>${claim.description || '-'}</td>
                            <td>${itemStatusHtml}</td>
                            <td>${receiptHtml}</td>
                        </tr>
                    `;
                    viewTableBody.insertAdjacentHTML('beforeend', rowHtml);
                });
            });

            // Real-time Item Decision Change Handler for Expense Report Approval
            window.handleItemDecisionChange = function(select, reportId, fullAdvance) {
                var claimId = select.getAttribute('data-claim-id');
                var amount = parseFloat(select.getAttribute('data-amount')) || 0;
                var decision = select.value;
                var rejectionInput = document.getElementById('item_rejection_reason_' + claimId);
                var approvedAmtInput = document.getElementById('item_approved_amt_' + claimId);

                if (decision === 'rejected') {
                    if (rejectionInput) {
                        rejectionInput.classList.remove('d-none');
                        rejectionInput.focus();
                    }
                    if (approvedAmtInput) approvedAmtInput.value = '0';
                } else {
                    if (rejectionInput) rejectionInput.classList.add('d-none');
                    if (approvedAmtInput) approvedAmtInput.value = amount.toString();
                }

                var modal = select.closest('.modal');
                if (modal) {
                    var totalApproved = 0;
                    var itemSelects = modal.querySelectorAll('.claim-item-decision-select');
                    itemSelects.forEach(function(sel) {
                        var itemAmt = parseFloat(sel.getAttribute('data-amount')) || 0;
                        if (sel.value === 'approved') {
                            totalApproved += itemAmt;
                        }
                    });

                    var mainApprovedInput = document.getElementById('approve_amount_input_' + reportId);
                    if (mainApprovedInput) {
                        mainApprovedInput.value = totalApproved.toFixed(2);
                        calculateApprovalNet(reportId, fullAdvance);
                    }
                }
            };

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
                var firstSelect = this.querySelector('.claim-item-decision-select');
                if (firstSelect) {
                    var fullAdv = parseFloat(firstSelect.getAttribute('data-full-advance')) || 0;
                    handleItemDecisionChange(firstSelect, id, fullAdv);
                } else {
                    var input = document.getElementById('approve_amount_input_' + id);
                    if (input) input.dispatchEvent(new Event('input'));
                }
                var select = document.getElementById('payout_channel_' + id);
                if (select) {
                    select.dispatchEvent(new Event('change'));
                }
            });
            // Handle "Claim Expense" / "Supplementary Claim" button clicks across tabs
            $(document).on('click', '.claim-travel-expense-btn, .claim-supplementary-btn', function (e) {
                e.preventDefault();
                var travelId = $(this).data('travel-id');
                var advanceId = $(this).data('cash-advance-id');
                var customTitle = $(this).data('title');

                // Open File Expense Claim Modal (#addReportModal) in current tab
                var addReportModalEl = document.getElementById('addReportModal');
                if (addReportModalEl) {
                    var modalInstance = bootstrap.Modal.getOrCreateInstance(addReportModalEl);
                    modalInstance.show();

                    // Pre-select Travel Trip, Title & Cash Advance dropdowns
                    setTimeout(function () {
                        if (customTitle) {
                            var titleInput = document.querySelector('#addReportModal input[name="title"]');
                            if (titleInput) titleInput.value = customTitle;
                        }
                        if (travelId && $('#report_travel_request').length) {
                            $('#report_travel_request').val(travelId).trigger('change');
                        }
                        if (advanceId && $('#report_cash_advance').length) {
                            $('#report_cash_advance').val(advanceId).trigger('change');
                        }
                    }, 200);
                }
            });

            @if($errors->any())
                @if($errors->has('destination') || $errors->has('start_date') || $errors->has('end_date') || $errors->has('estimated_budget'))
                    var modal = new bootstrap.Modal(document.getElementById('addTravelModal'));
                    modal.show();
                @elseif($errors->has('amount'))
                    var modal = new bootstrap.Modal(document.getElementById('addAdvanceModal'));
                    modal.show();
                @elseif($errors->has('title') || $errors->has('claims') || $errors->has('claims.*'))
                    var modal = new bootstrap.Modal(document.getElementById('addReportModal'));
                    modal.show();
                @endif
            @endif
        });
    </script>
@endpush
