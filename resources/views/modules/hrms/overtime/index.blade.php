@extends('layouts.duralux')

@section('title', 'Overtime Applications | SaaS ERP')
@section('page-title', 'Overtime Applications')
@section('breadcrumb', 'HRMS / Overtime')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if($isAdmin)
            <button type="button" class="btn btn-outline-secondary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#overtimeSettingsModal" style="height: 38px;">
                <i class="feather-settings"></i> Policies
            </button>
        @endif
        <button type="button" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#applyOvertimeModal" style="height: 38px;">
            <i class="feather-plus"></i> Apply Overtime
        </button>
    </div>
@endsection

@push('styles')
    <style>
        /* Action Status Dropdown Styling */
        .btn-status-dropdown {
            background-color: #7c6f6c !important;
            color: #ffffff !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            height: 36px !important;
            border-radius: 8px !important;
            width: 120px !important;
            border: none !important;
            padding: 0 12px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .btn-status-dropdown:hover,
        .btn-status-dropdown:focus,
        .btn-status-dropdown:active {
            background-color: #6a5e5a !important;
            color: #ffffff !important;
        }
        .btn-status-dropdown::after {
            display: inline-block;
            margin-left: 8px;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            color: #ffffff !important;
        }
        .status-dropdown-menu {
            min-width: 130px !important;
            width: 130px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
            padding: 6px !important;
            background: #ffffff !important;
        }
        .status-dropdown-menu .dropdown-item {
            text-align: center !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            color: #1e293b !important;
            background: transparent !important;
            transition: all 0.2s ease;
        }
        .status-dropdown-menu .dropdown-item:hover {
            background-color: #f8fafc !important;
            color: #1e293b !important;
        }
        .status-dropdown-menu .dropdown-item.active-status {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            font-weight: 700 !important;
        }
    </style>
@endpush

@section('content')
    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="feather-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="feather-alert-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="feather-alert-triangle me-2"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Requests</div>
                        <h3 class="fw-bold mb-0 text-dark mt-1">{{ number_format($totalRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(13, 110, 253, 0.1);">
                        <i class="feather-clock fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pending Approval</div>
                        <h3 class="fw-bold mb-0 text-warning mt-1">{{ number_format($pendingRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-warning text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(255, 193, 7, 0.1);">
                        <i class="feather-clock fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Approved</div>
                        <h3 class="fw-bold mb-0 text-success mt-1">{{ number_format($approvedRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(25, 135, 84, 0.1);">
                        <i class="feather-check-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Rejected</div>
                        <h3 class="fw-bold mb-0 text-danger mt-1">{{ number_format($rejectedRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-danger text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(220, 53, 69, 0.1);">
                        <i class="feather-x-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Policy Overview (Admins) --}}
    @if($isAdmin)
        <div class="card border-0 shadow-sm mb-4 bg-light">
            <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-info text-primary fs-4"></i>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Active Overtime Policies</h6>
                        <p class="text-muted fs-11 mb-0">Threshold: <strong>{{ number_format($tenantSettings['auto_overtime_threshold_hours'], 1) }} hours</strong> (auto-approved on punch) | Multiplier: <strong>{{ number_format($tenantSettings['overtime_rate_multiplier'], 2) }}x</strong> rate</p>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm rounded text-uppercase fw-bold" data-bs-toggle="modal" data-bs-target="#overtimeSettingsModal">
                    Modify Policies
                </button>
            </div>
        </div>
    @endif

    {{-- Requests Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-clock me-2 text-primary"></i> Overtime Applications</h5>
                <p class="text-muted fs-12 mb-0">Review and manage employee overtime requests</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('hrms.overtime.index') }}" class="d-flex align-items-center gap-2 m-0">
                    <!-- Search Input -->
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search employee..." value="{{ request('search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <!-- Status Filter -->
                    <div style="min-width: 140px;">
                        <select name="status" class="form-select fs-13" onchange="this.form.submit()" style="height: 38px;">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-light border" style="height: 38px;"><i class="feather-filter"></i></button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="overflow: visible;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Time Frame</th>
                            <th>Requested Hours</th>
                            <th>Approved Hours</th>
                            <th>Comp. Type</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold; background-color: rgba(13, 110, 253, 0.1);">
                                            {{ substr($req->employee->full_name, 0, 2) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $req->employee->full_name }}</div>
                                            <div class="text-muted fs-11">{{ $req->employee->employee_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $req->date->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ substr($req->start_time, 0, 5) }} - {{ substr($req->end_time, 0, 5) }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ number_format($req->duration_hours, 1) }} hrs</div>
                                </td>
                                <td>
                                    @if($req->status === 'approved' && $req->approved_duration_hours !== null)
                                        <div class="fw-bold text-success">{{ number_format($req->approved_duration_hours, 1) }} hrs</div>
                                    @else
                                        <span class="text-muted fs-11">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-uppercase fs-10" style="background-color: {{ $req->compensation_type === 'comp_off' ? 'rgba(13, 110, 253, 0.1)' : 'rgba(25, 135, 84, 0.1)' }}; color: {{ $req->compensation_type === 'comp_off' ? '#0d6efd' : '#198754' }};">
                                        {{ $req->compensation_type === 'comp_off' ? 'Comp Off' : 'Payout' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge text-uppercase fs-10" style="background-color: {{ $req->status === 'approved' ? 'rgba(25, 135, 84, 0.1)' : ($req->status === 'rejected' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)') }}; color: {{ $req->status === 'approved' ? '#198754' : ($req->status === 'rejected' ? '#dc3545' : '#ffc107') }};">
                                        {{ $req->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if($isAdmin)
                                        <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                            <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span>{{ $req->status === 'approved' ? 'Approved' : ($req->status === 'rejected' ? 'Rejected' : 'Pending') }}</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu">
                                                <li>
                                                    <button type="button" class="dropdown-item {{ $req->status === 'approved' ? 'active-status' : '' }}" onclick="handleDecision('approve', {{ $req->id }}, {{ $req->duration_hours }})">
                                                        Approved
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item {{ $req->status === 'rejected' ? 'active-status' : '' }}" onclick="handleDecision('reject', {{ $req->id }}, {{ $req->duration_hours }})">
                                                        Rejected
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item {{ $req->status === 'pending' ? 'active-status' : '' }}" onclick="handleDecision('pending', {{ $req->id }}, {{ $req->duration_hours }})">
                                                        Pending
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    @else
                                        <span class="text-muted fs-11">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted fs-13">No overtime requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Apply Overtime Modal --}}
    <div class="modal fade" id="applyOvertimeModal" tabindex="-1" aria-labelledby="applyOvertimeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('hrms.overtime.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="applyOvertimeModalLabel">Apply Overtime</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($isAdmin)
                            <div class="mb-3">
                                <label class="form-label fw-bold">Employee</label>
                                <select name="employee_id" class="form-select" required>
                                    <option value="">Select Employee</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required value="18:00">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">End Time</label>
                                <input type="time" name="end_time" class="form-control" required value="20:00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Compensation Type</label>
                            <select name="compensation_type" class="form-select" required>
                                <option value="payout">Payout (Financial Payout)</option>
                                <option value="comp_off">Comp-Off (Credit to Leave Balance)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="Describe details of work performed..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Attachment (Optional)</label>
                            <input type="file" name="attachment" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Overtime Settings Modal --}}
    @if($isAdmin)
        <div class="modal fade" id="overtimeSettingsModal" tabindex="-1" aria-labelledby="overtimeSettingsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('hrms.overtime.update-settings') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="overtimeSettingsModalLabel">Overtime Policy Configurations</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Auto-Consider Overtime Threshold (Hours)</label>
                                <input type="number" name="auto_overtime_threshold_hours" class="form-control" required step="0.5" min="0" value="{{ $tenantSettings['auto_overtime_threshold_hours'] }}">
                                <div class="form-text text-muted">If actual worked extra hours equal or exceed this number, the system automatically logs and approves overtime.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Minimum Overtime Request Hours</label>
                                <input type="number" name="min_overtime_request_hours" class="form-control" required step="0.5" min="0.5" value="{{ $tenantSettings['min_overtime_request_hours'] ?? 0.5 }}">
                                <div class="form-text text-muted">Minimum hours required for a manual overtime request (e.g. 2.0 hrs).</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Overtime Calculation Multiplier</label>
                                <input type="number" name="overtime_rate_multiplier" class="form-control" required step="0.1" min="1.0" value="{{ $tenantSettings['overtime_rate_multiplier'] }}">
                                <div class="form-text text-muted">Multiplication rate for overtime compensation calculations (e.g. 1.5x, 2.0x).</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Decision Form (hidden) --}}
    <form id="decisionForm" action="" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="action" id="decisionAction" value="">
        <input type="hidden" name="rejection_reason" id="decisionReason" value="">
        <input type="hidden" name="approved_duration_hours" id="decisionApprovedHours" value="">
    </form>

    {{-- Approve Modal --}}
    <div class="modal fade" id="approveOvertimeModal" tabindex="-1" aria-labelledby="approveOvertimeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="approveOvertimeModalLabel">Approve Overtime</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Approved Hours</label>
                        <input type="number" id="approveHoursInput" class="form-control" step="0.5" min="0.5" placeholder="e.g. 2.0">
                        <div class="form-text text-muted">Enter the actual hours to approve (can differ from requested hours).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApproveBtn">Approve</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div class="modal fade" id="rejectOvertimeModal" tabindex="-1" aria-labelledby="rejectOvertimeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="rejectOvertimeModalLabel">Reject Overtime</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rejection Reason <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea id="rejectReasonInput" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRejectBtn">Reject</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var _pendingDecisionId = null;

        function handleDecision(action, requestId, requestedHours) {
            _pendingDecisionId = requestId;
            const form = document.getElementById('decisionForm');
            form.action = `{{ url('hrms/overtime') }}/${requestId}/update-status`;

            if (action === 'approve') {
                document.getElementById('approveHoursInput').value = requestedHours;
                var modal = new bootstrap.Modal(document.getElementById('approveOvertimeModal'));
                modal.show();
            } else if (action === 'reject') {
                document.getElementById('rejectReasonInput').value = '';
                var modal = new bootstrap.Modal(document.getElementById('rejectOvertimeModal'));
                modal.show();
            } else if (action === 'pending') {
                document.getElementById('decisionAction').value = 'pending';
                document.getElementById('decisionReason').value = '';
                document.getElementById('decisionApprovedHours').value = '';
                form.submit();
            }
        }

        document.getElementById('confirmApproveBtn').addEventListener('click', function () {
            const hoursVal = parseFloat(document.getElementById('approveHoursInput').value);
            if (isNaN(hoursVal) || hoursVal <= 0) {
                alert('Please enter a valid positive number for hours.');
                return;
            }
            const form = document.getElementById('decisionForm');
            form.action = `{{ url('hrms/overtime') }}/${_pendingDecisionId}/update-status`;
            document.getElementById('decisionAction').value = 'approved';
            document.getElementById('decisionApprovedHours').value = hoursVal;
            document.getElementById('decisionReason').value = '';
            bootstrap.Modal.getInstance(document.getElementById('approveOvertimeModal')).hide();
            form.submit();
        });

        document.getElementById('confirmRejectBtn').addEventListener('click', function () {
            const reason = document.getElementById('rejectReasonInput').value.trim();
            const form = document.getElementById('decisionForm');
            form.action = `{{ url('hrms/overtime') }}/${_pendingDecisionId}/update-status`;
            document.getElementById('decisionAction').value = 'rejected';
            document.getElementById('decisionReason').value = reason;
            document.getElementById('decisionApprovedHours').value = '';
            bootstrap.Modal.getInstance(document.getElementById('rejectOvertimeModal')).hide();
            form.submit();
        });
    </script>
@endpush
