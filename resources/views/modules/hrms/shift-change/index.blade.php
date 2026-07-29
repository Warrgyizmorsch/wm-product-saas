@extends('layouts.duralux')

@section('title', 'Shift Change Applications | SaaS ERP')
@section('page-title', 'Shift Change Applications')
@section('breadcrumb', 'HRMS / Shift Change')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#applyShiftChangeModal" style="height: 38px;">
            <i class="feather-plus"></i> Apply Shift Change
        </button>
    </div>
@endsection

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
                        <i class="feather-git-pull-request fs-4"></i>
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

    {{-- Requests Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-git-pull-request me-2 text-primary"></i> Shift Change Applications</h5>
                <p class="text-muted fs-12 mb-0">Review and manage shift change applications</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('hrms.shift-change.index') }}" class="d-flex align-items-center gap-2 m-0">
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
                            <th>Type</th>
                            <th>Effective Period</th>
                            <th>Recurring Days</th>
                            <th>Current Shift</th>
                            <th>Requested Shift</th>
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
                                    <span class="badge text-uppercase fs-10" style="background-color: {{ $req->type === 'permanent' ? 'rgba(25, 135, 84, 0.1)' : ($req->type === 'recurring' ? 'rgba(13, 110, 253, 0.1)' : 'rgba(108, 117, 125, 0.1)') }}; color: {{ $req->type === 'permanent' ? '#198754' : ($req->type === 'recurring' ? '#0d6efd' : '#6c757d') }};">
                                        {{ $req->type }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $req->start_date->format('d M Y') }}</div>
                                    @if($req->type === 'temporary' && $req->end_date)
                                        <div class="text-muted fs-11">to {{ $req->end_date->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($req->type === 'recurring' && is_array($req->recurring_days))
                                        @php
                                            $dayNames = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                                            $mapped = array_map(fn($d) => $dayNames[$d] ?? '', $req->recurring_days);
                                        @endphp
                                        <div class="fs-11 text-dark fw-medium">{{ implode(', ', $mapped) }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($req->currentShift)
                                        <div class="fw-medium text-dark">{{ $req->currentShift->name }}</div>
                                        <div class="text-muted fs-10">{{ substr($req->currentShift->start_time, 0, 5) }} - {{ substr($req->currentShift->end_time, 0, 5) }}</div>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Day Off</span>
                                    @endif
                                </td>
                                <td>
                                    @if($req->requestedShift)
                                        <div class="fw-medium text-dark">{{ $req->requestedShift->name }}</div>
                                        <div class="text-muted fs-10">{{ substr($req->requestedShift->start_time, 0, 5) }} - {{ substr($req->requestedShift->end_time, 0, 5) }}</div>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Day Off</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-uppercase fs-10" style="background-color: {{ $req->status === 'approved' ? 'rgba(25, 135, 84, 0.1)' : ($req->status === 'rejected' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)') }}; color: {{ $req->status === 'approved' ? '#198754' : ($req->status === 'rejected' ? '#dc3545' : '#ffc107') }};">
                                        {{ $req->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if($isAdmin && $req->status === 'pending')
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <button type="button" class="btn btn-success btn-xs" onclick="handleDecision('approve', {{ $req->id }})">
                                                Approve
                                            </button>
                                            <button type="button" class="btn btn-danger btn-xs" onclick="handleDecision('reject', {{ $req->id }})">
                                                Reject
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted fs-11">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted fs-13">No shift change requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Apply Shift Change Modal --}}
    <div class="modal fade" id="applyShiftChangeModal" tabindex="-1" aria-labelledby="applyShiftChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('hrms.shift-change.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="applyShiftChangeModalLabel">Apply Shift Change</h5>
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
                            <label class="form-label fw-bold">Type</label>
                            <select name="type" id="shift_change_type" class="form-select" required>
                                <option value="temporary">Temporary (Date Range)</option>
                                <option value="permanent">Permanent (Effective Date onwards)</option>
                                <option value="recurring">Recurring Weekdays</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="mb-3" id="end_date_container">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="mb-3 d-none" id="recurring_days_container">
                            <label class="form-label fw-bold d-block">Recurring Weekdays</label>
                            <div class="d-flex flex-wrap gap-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="1" id="day_mon">
                                    <label class="form-check-label fs-12" for="day_mon">Mon</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="2" id="day_tue">
                                    <label class="form-check-label fs-12" for="day_tue">Tue</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="3" id="day_wed">
                                    <label class="form-check-label fs-12" for="day_wed">Wed</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="4" id="day_thu">
                                    <label class="form-check-label fs-12" for="day_thu">Thu</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="5" id="day_fri">
                                    <label class="form-check-label fs-12" for="day_fri">Fri</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="6" id="day_sat">
                                    <label class="form-check-label fs-12" for="day_sat">Sat</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="0" id="day_sun">
                                    <label class="form-check-label fs-12" for="day_sun">Sun</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Requested Shift</label>
                            <select name="requested_shift_id" class="form-select">
                                <option value="">Select Shift (Empty for Day Off)</option>
                                @foreach($shifts as $sf)
                                    <option value="{{ $sf->id }}">{{ $sf->name }} ({{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="Describe the reason for change..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Attachment (Optional)</label>
                            <input type="file" name="attachment" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Decision/Approval Forms --}}
    <form id="decisionForm" action="" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="action" id="decisionAction" value="">
        <input type="hidden" name="rejection_reason" id="decisionReason" value="">
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('shift_change_type').addEventListener('change', function () {
            const val = this.value;
            const endDateContainer = document.getElementById('end_date_container');
            const recurringContainer = document.getElementById('recurring_days_container');

            if (val === 'temporary') {
                endDateContainer.classList.remove('d-none');
                recurringContainer.classList.add('d-none');
            } else if (val === 'permanent') {
                endDateContainer.classList.add('d-none');
                recurringContainer.classList.add('d-none');
            } else if (val === 'recurring') {
                endDateContainer.classList.add('d-none');
                recurringContainer.classList.remove('d-none');
            }
        });

        function handleDecision(action, requestId) {
            const form = document.getElementById('decisionForm');
            form.action = `{{ url('hrms/shift-change') }}/${requestId}/update-status`;
            document.getElementById('decisionAction').value = action === 'approve' ? 'approved' : 'rejected';

            if (action === 'reject') {
                const reason = prompt('Please enter a rejection reason:');
                if (reason === null) return; // User cancelled
                document.getElementById('decisionReason').value = reason;
            } else {
                document.getElementById('decisionReason').value = '';
            }

            form.submit();
        }
    </script>
@endpush
