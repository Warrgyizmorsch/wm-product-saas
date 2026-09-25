@php
    $leavesList = !empty($pendingLeaves) && count($pendingLeaves) > 0 ? $pendingLeaves : [
        (object)[
            'id' => 1,
            'employee_id' => 1,
            'employee' => (object)['full_name' => 'Rahul Sharma', 'employee_id' => 'DT-0001'],
            'leaveType' => (object)['name' => 'Casual Leave'],
            'start_date' => '2026-09-24',
            'end_date' => '2026-09-26',
            'reason' => 'Family occasion and personal travel',
            'status' => 'pending'
        ],
        (object)[
            'id' => 2,
            'employee_id' => 4,
            'employee' => (object)['full_name' => 'Priya Patel', 'employee_id' => 'DT-0004'],
            'leaveType' => (object)['name' => 'Sick Leave'],
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-29',
            'reason' => 'Doctor appointment and medical leave',
            'status' => 'pending'
        ],
        (object)[
            'id' => 3,
            'employee_id' => 12,
            'employee' => (object)['full_name' => 'Amit Kumar', 'employee_id' => 'DT-0012'],
            'leaveType' => (object)['name' => 'Earned Leave'],
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'reason' => 'Annual family vacation',
            'status' => 'pending'
        ],
        (object)[
            'id' => 4,
            'employee_id' => 18,
            'employee' => (object)['full_name' => 'Neha Singh', 'employee_id' => 'DT-0018'],
            'leaveType' => (object)['name' => 'Casual Leave'],
            'start_date' => '2026-10-08',
            'end_date' => '2026-10-09',
            'reason' => 'Personal urgent work',
            'status' => 'pending'
        ],
        (object)[
            'id' => 5,
            'employee_id' => 25,
            'employee' => (object)['full_name' => 'Vikas Reddy', 'employee_id' => 'DT-0025'],
            'leaveType' => (object)['name' => 'Unpaid Leave'],
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-14',
            'reason' => 'Relocation travel',
            'status' => 'pending'
        ],
    ];

    $wfhList = !empty($pendingWfh) && count($pendingWfh) > 0 ? $pendingWfh : [
        (object)[
            'id' => 1,
            'employee_id' => 1,
            'employee' => (object)['full_name' => 'Rahul Sharma', 'employee_id' => 'DT-0001'],
            'type' => 'Full Day WFH',
            'start_date' => '2026-09-25',
            'end_date' => '2026-09-25',
            'reason' => 'Internet maintenance at home office',
            'status' => 'pending'
        ],
        (object)[
            'id' => 2,
            'employee_id' => 4,
            'employee' => (object)['full_name' => 'Priya Patel', 'employee_id' => 'DT-0004'],
            'type' => 'Flexible Remote',
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-30',
            'reason' => 'Attending remote client delivery sprint',
            'status' => 'pending'
        ],
        (object)[
            'id' => 3,
            'employee_id' => 18,
            'employee' => (object)['full_name' => 'Neha Singh', 'employee_id' => 'DT-0018'],
            'type' => 'Full Day WFH',
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-02',
            'reason' => 'Severe weather forecast & transit delay',
            'status' => 'pending'
        ],
    ];

    $correctionsList = !empty($pendingCorrections) && count($pendingCorrections) > 0 ? $pendingCorrections : [
        (object)[
            'id' => 1,
            'employee_id' => 1,
            'employee' => (object)['full_name' => 'Rahul Sharma', 'employee_id' => 'DT-0001'],
            'date' => '2026-09-22',
            'type' => 'Missed Check-In',
            'time' => '09:32 AM',
            'reason' => 'Biometric scanner timeout at reception',
            'status' => 'pending'
        ],
        (object)[
            'id' => 2,
            'employee_id' => 12,
            'employee' => (object)['full_name' => 'Amit Kumar', 'employee_id' => 'DT-0012'],
            'date' => '2026-09-21',
            'type' => 'Missed Check-Out',
            'time' => '06:45 PM',
            'reason' => 'Left directly for client site visit',
            'status' => 'pending'
        ],
        (object)[
            'id' => 3,
            'employee_id' => 25,
            'employee' => (object)['full_name' => 'Vikas Reddy', 'employee_id' => 'DT-0025'],
            'date' => '2026-09-20',
            'type' => 'Shift Regularization',
            'time' => '10:00 AM',
            'reason' => 'Emergency server deployment overnight',
            'status' => 'pending'
        ],
    ];

    $expensesList = !empty($pendingExpenses) && count($pendingExpenses) > 0 ? $pendingExpenses : [
        (object)[
            'id' => 1,
            'employee_id' => 1,
            'employee' => (object)['full_name' => 'Rahul Sharma', 'employee_id' => 'DT-0001'],
            'category' => 'Client Lunch & Travel',
            'total_amount' => 3450.00,
            'currency' => 'INR',
            'reason' => 'Quarterly review dinner with enterprise partner',
            'status' => 'pending'
        ],
        (object)[
            'id' => 2,
            'employee_id' => 18,
            'employee' => (object)['full_name' => 'Neha Singh', 'employee_id' => 'DT-0018'],
            'category' => 'Hardware & Peripheral',
            'total_amount' => 1899.00,
            'currency' => 'INR',
            'reason' => 'USB-C hub & wireless mouse replacement',
            'status' => 'pending'
        ],
        (object)[
            'id' => 3,
            'employee_id' => 12,
            'employee' => (object)['full_name' => 'Amit Kumar', 'employee_id' => 'DT-0012'],
            'category' => 'Flight & Hotel Booking',
            'total_amount' => 12400.00,
            'currency' => 'INR',
            'reason' => 'Regional branch tech training summit',
            'status' => 'pending'
        ],
    ];
@endphp

<style>
    /* Common UI Navigation Pills */
    .pending-approval-pills .nav-link {
        color: #64748b !important;
        font-weight: 600;
        font-size: 11.5px;
        padding: 5px 12px;
        border-radius: 8px;
        background: transparent;
        border: none;
        transition: all 0.15s ease-in-out;
    }
    .pending-approval-pills .nav-link:hover {
        color: #1e293b !important;
    }
    .pending-approval-pills .nav-link.active {
        background: #ffffff !important;
        color: #4f46e5 !important;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        font-weight: 700;
    }

    .pending-approvals-widget {
        border-radius: 16px;
        background: #ffffff;
    }
    .pending-approvals-widget .card-body {
        min-height: 280px;
    }
    .pending-approvals-widget .table-responsive {
        overflow: visible !important;
    }
    .pending-approvals-widget .table tbody tr {
        transition: background-color 0.15s ease;
    }
    .pending-approvals-widget .table tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* Action Status Dropdown Styling (matching Shift & Overtime and Leaves module) */
    .btn-status-dropdown {
        background-color: #7c6f6c !important;
        color: #ffffff !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        height: 32px !important;
        border-radius: 8px !important;
        min-width: 105px !important;
        border: none !important;
        padding: 0 10px !important;
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
        margin-left: 6px;
        vertical-align: 0.255em;
        content: "";
        border-top: 0.3em solid;
        border-right: 0.3em solid transparent;
        border-bottom: 0;
        border-left: 0.3em solid transparent;
        color: #ffffff !important;
    }

    .status-dropdown-menu {
        min-width: 145px !important;
        border-radius: 8px !important;
        border: none !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.12) !important;
        padding: 6px !important;
        background: #ffffff !important;
    }
    .status-dropdown-menu .dropdown-item {
        font-size: 12px !important;
        font-weight: 500 !important;
        padding: 7px 10px !important;
        border-radius: 6px !important;
        color: #1e293b !important;
        background: transparent !important;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        gap: 6px;
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

<div class="card border-0 mb-0 shadow-sm pending-approvals-widget">
    <div class="card-header bg-white pt-3 pb-2 px-3 d-flex align-items-center justify-content-between border-bottom-0 flex-wrap gap-2">
        <!-- Title with Avatar Icon -->
        <div class="d-flex align-items-center gap-2" style="gap: 8px;">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 30px; height: 30px; background: #eef2ff; border-radius: 8px; color: #4f46e5;">
                <i class="feather-inbox fs-14"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark" style="font-size: 15px; color: #1e293b !important; font-weight: 700;">Pending Approvals Action Center</h6>
        </div>

        <!-- Right Side Nav Pills -->
        <ul class="nav nav-pills border-0 p-1 rounded-3 pending-approval-pills d-inline-flex align-items-center gap-1" id="pills-approval-tab" role="tablist" style="background: #f1f5f9; border-radius: 10px;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-inline-flex align-items-center gap-1" id="pills-leaves-tab" data-bs-toggle="pill" data-bs-target="#pills-leaves" type="button" role="tab" aria-controls="pills-leaves" aria-selected="true">
                    Leaves <span class="badge rounded-pill px-1.5 py-0.5 fs-10 fw-bold ms-1" style="background: #eef2ff; color: #4f46e5;">{{ count($leavesList) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-inline-flex align-items-center gap-1" id="pills-wfh-tab" data-bs-toggle="pill" data-bs-target="#pills-wfh" type="button" role="tab" aria-controls="pills-wfh" aria-selected="false">
                    WFH <span class="badge rounded-pill px-1.5 py-0.5 fs-10 fw-bold ms-1" style="background: #ccfbf1; color: #0d9488;">{{ count($wfhList) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-inline-flex align-items-center gap-1" id="pills-punches-tab" data-bs-toggle="pill" data-bs-target="#pills-punches" type="button" role="tab" aria-controls="pills-punches" aria-selected="false">
                    Punches <span class="badge rounded-pill px-1.5 py-0.5 fs-10 fw-bold ms-1" style="background: #fef3c7; color: #d97706;">{{ count($correctionsList) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-inline-flex align-items-center gap-1" id="pills-expenses-tab" data-bs-toggle="pill" data-bs-target="#pills-expenses" type="button" role="tab" aria-controls="pills-expenses" aria-selected="false">
                    Expenses <span class="badge rounded-pill px-1.5 py-0.5 fs-10 fw-bold ms-1" style="background: #dcfce7; color: #16a34a;">{{ count($expensesList) }}</span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Table Container -->
    <div class="card-body p-0">
        <div class="tab-content" id="pills-approval-tabContent">
            <!-- 1. LEAVES TAB -->
            <div class="tab-pane fade show active" id="pills-leaves" role="tabpanel" aria-labelledby="pills-leaves-tab">
                <div class="table-responsive" style="overflow: visible;">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 18%;">LEAVE TYPE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">TIMELINE & DAYS</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 14%;">STATUS</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; width: 24%;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leavesList as $leave)
                                @php
                                    $startDate = isset($leave->start_date) ? \Carbon\Carbon::parse($leave->start_date) : \Carbon\Carbon::today();
                                    $endDate = isset($leave->end_date) ? \Carbon\Carbon::parse($leave->end_date) : \Carbon\Carbon::today();
                                    $daysCount = $startDate->diffInDays($endDate) + 1;
                                    $empName = is_object($leave->employee) ? ($leave->employee->full_name ?? 'Rahul Sharma') : 'Rahul Sharma';
                                    $empCode = is_object($leave->employee) ? ($leave->employee->employee_id ?? 'DT-0001') : 'DT-0001';
                                    $leaveTypeName = is_object($leave->leaveType) ? ($leave->leaveType->name ?? 'Casual Leave') : 'Casual Leave';
                                    $leaveId = is_object($leave) && isset($leave->id) ? $leave->id : null;
                                    $leaveStatus = strtolower(is_object($leave) && isset($leave->status) ? $leave->status : 'pending');
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5">{{ $empCode }}</div>
                                    </td>

                                    <!-- LEAVE TYPE -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate gap-1.5">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #3b82f6;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate">{{ $leaveTypeName }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5 ps-2.5" title="{{ $leave->reason ?? '' }}">{{ $leave->reason ?? 'Personal reason' }}</div>
                                    </td>

                                    <!-- TIMELINE & DAYS -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-12 text-nowrap">
                                            {{ $startDate->format('d M') }} &ndash; {{ $endDate->format('d M Y') }}
                                        </div>
                                        <div class="fs-11 text-muted mt-0.5">
                                            <span class="badge px-1.5 py-0.5 fs-10 fw-bold rounded-2" style="background: #eff6ff; color: #3b82f6;">{{ $daysCount }} {{ Str::plural('Day', $daysCount) }}</span>
                                        </div>
                                    </td>

                                    <!-- STATUS -->
                                    <td class="py-2.5 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        @if($leaveStatus === 'approved')
                                            <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11 text-capitalize">Approved</span>
                                        @elseif($leaveStatus === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11 text-capitalize">Rejected</span>
                                        @elseif($leaveStatus === 'unauthorized')
                                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11 text-capitalize">Unauthorized</span>
                                        @elseif($leaveStatus === 'unpaid')
                                            <span class="badge bg-soft-info text-info px-2.5 py-1 rounded-pill fs-11 text-capitalize">Unpaid</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11 text-capitalize">Pending</span>
                                        @endif
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9; white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap" style="gap: 8px;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leaves.index') ? route('hrms.leaves.index') : '#' }}"
                                               class="btn btn-sm btn-soft-primary"
                                               title="View Details"
                                               style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                <i class="feather-eye fs-14"></i>
                                            </a>

                                            <div class="dropdown d-inline-block position-relative">
                                                <button class="btn btn-sm dropdown-toggle py-1 px-2.5 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Change Status">
                                                    <span>{{ ucfirst($leaveStatus) }}</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu shadow" style="z-index: 1060;">
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="approved">
                                                                <button type="submit" class="dropdown-item text-success {{ $leaveStatus === 'approved' ? 'active-status' : '' }}">
                                                                    <i class="feather-check-circle fs-12 text-success"></i>
                                                                    <span>Approve Request</span>
                                                                </button>
                                                            </form>
                                                        @elseif($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.approve'))
                                                            <form action="{{ route('hrms.leaves.approve', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-success {{ $leaveStatus === 'approved' ? 'active-status' : '' }}">
                                                                    <i class="feather-check-circle fs-12 text-success"></i>
                                                                    <span>Approve Request</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-success" href="javascript:void(0)" onclick="alert('Leave request approved!')">
                                                                <i class="feather-check-circle fs-12 text-success"></i>
                                                                <span>Approve Request</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="rejected">
                                                                <button type="submit" class="dropdown-item text-danger {{ $leaveStatus === 'rejected' ? 'active-status' : '' }}">
                                                                    <i class="feather-x-circle fs-12 text-danger"></i>
                                                                    <span>Reject Request</span>
                                                                </button>
                                                            </form>
                                                        @elseif($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.reject'))
                                                            <form action="{{ route('hrms.leaves.reject', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-danger {{ $leaveStatus === 'rejected' ? 'active-status' : '' }}">
                                                                    <i class="feather-x-circle fs-12 text-danger"></i>
                                                                    <span>Reject Request</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="alert('Leave request rejected!')">
                                                                <i class="feather-x-circle fs-12 text-danger"></i>
                                                                <span>Reject Request</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="unauthorized">
                                                                <button type="submit" class="dropdown-item {{ $leaveStatus === 'unauthorized' ? 'active-status' : '' }}">
                                                                    <i class="feather-alert-triangle fs-12 text-warning"></i>
                                                                    <span>Unauthorized</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="alert('Marked as unauthorized!')">
                                                                <i class="feather-alert-triangle fs-12 text-warning"></i>
                                                                <span>Unauthorized</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="unpaid">
                                                                <button type="submit" class="dropdown-item {{ $leaveStatus === 'unpaid' ? 'active-status' : '' }}">
                                                                    <i class="feather-dollar-sign fs-12 text-info"></i>
                                                                    <span>Unpaid</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="alert('Marked as unpaid!')">
                                                                <i class="feather-dollar-sign fs-12 text-info"></i>
                                                                <span>Unpaid</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="pending">
                                                                <button type="submit" class="dropdown-item {{ $leaveStatus === 'pending' ? 'active-status' : '' }}">
                                                                    <i class="feather-clock fs-12 text-muted"></i>
                                                                    <span>Pending</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="alert('Reset to pending!')">
                                                                <i class="feather-clock fs-12 text-muted"></i>
                                                                <span>Pending</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. WFH TAB -->
            <div class="tab-pane fade" id="pills-wfh" role="tabpanel" aria-labelledby="pills-wfh-tab">
                <div class="table-responsive" style="overflow: visible;">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 18%;">REQUEST TYPE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">TIMELINE & DAYS</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 14%;">STATUS</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; width: 24%;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wfhList as $wfh)
                                @php
                                    $wfhStart = isset($wfh->start_date) ? \Carbon\Carbon::parse($wfh->start_date) : \Carbon\Carbon::today();
                                    $wfhEnd = isset($wfh->end_date) ? \Carbon\Carbon::parse($wfh->end_date) : \Carbon\Carbon::today();
                                    $wfhDays = $wfhStart->diffInDays($wfhEnd) + 1;
                                    $empName = is_object($wfh->employee) ? ($wfh->employee->full_name ?? 'Employee') : 'Employee';
                                    $empCode = is_object($wfh->employee) ? ($wfh->employee->employee_id ?? 'DT-0001') : 'DT-0001';
                                    $wfhTypeName = $wfh->type ?? 'Work From Home';
                                    $wfhId = is_object($wfh) && isset($wfh->id) ? $wfh->id : null;
                                    $wfhStatus = strtolower(is_object($wfh) && isset($wfh->status) ? $wfh->status : 'pending');
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5">{{ $empCode }}</div>
                                    </td>

                                    <!-- REQUEST TYPE -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate gap-1.5">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #0d9488;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate">{{ $wfhTypeName }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5 ps-2.5" title="{{ $wfh->reason ?? '' }}">{{ $wfh->reason ?? 'Remote work' }}</div>
                                    </td>

                                    <!-- TIMELINE & DAYS -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-12 text-nowrap">
                                            {{ $wfhStart->format('d M') }} &ndash; {{ $wfhEnd->format('d M Y') }}
                                        </div>
                                        <div class="fs-11 text-muted mt-0.5">
                                            <span class="badge px-1.5 py-0.5 fs-10 fw-bold rounded-2" style="background: #ccfbf1; color: #0d9488;">{{ $wfhDays }} {{ Str::plural('Day', $wfhDays) }}</span>
                                        </div>
                                    </td>

                                    <!-- STATUS -->
                                    <td class="py-2.5 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        @if($wfhStatus === 'approved')
                                            <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11 text-capitalize">Approved</span>
                                        @elseif($wfhStatus === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11 text-capitalize">Rejected</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11 text-capitalize">Pending</span>
                                        @endif
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9; white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap" style="gap: 8px;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.wfh.index') ? route('hrms.wfh.index') : '#' }}"
                                               class="btn btn-sm btn-soft-primary"
                                               title="View Details"
                                               style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                <i class="feather-eye fs-14"></i>
                                            </a>

                                            <div class="dropdown d-inline-block position-relative">
                                                <button class="btn btn-sm dropdown-toggle py-1 px-2.5 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Change Status">
                                                    <span>{{ ucfirst($wfhStatus) }}</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu shadow" style="z-index: 1060;">
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.update-status'))
                                                            <form action="{{ route('hrms.wfh.update-status', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="approved">
                                                                <button type="submit" class="dropdown-item text-success {{ $wfhStatus === 'approved' ? 'active-status' : '' }}">
                                                                    <i class="feather-check-circle fs-12 text-success"></i>
                                                                    <span>Approve WFH</span>
                                                                </button>
                                                            </form>
                                                        @elseif($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.approve'))
                                                            <form action="{{ route('hrms.wfh.approve', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-success {{ $wfhStatus === 'approved' ? 'active-status' : '' }}">
                                                                    <i class="feather-check-circle fs-12 text-success"></i>
                                                                    <span>Approve WFH</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-success" href="javascript:void(0)" onclick="alert('WFH request approved!')">
                                                                <i class="feather-check-circle fs-12 text-success"></i>
                                                                <span>Approve WFH</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.update-status'))
                                                            <form action="{{ route('hrms.wfh.update-status', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="rejected">
                                                                <button type="submit" class="dropdown-item text-danger {{ $wfhStatus === 'rejected' ? 'active-status' : '' }}">
                                                                    <i class="feather-x-circle fs-12 text-danger"></i>
                                                                    <span>Reject WFH</span>
                                                                </button>
                                                            </form>
                                                        @elseif($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.reject'))
                                                            <form action="{{ route('hrms.wfh.reject', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-danger {{ $wfhStatus === 'rejected' ? 'active-status' : '' }}">
                                                                    <i class="feather-x-circle fs-12 text-danger"></i>
                                                                    <span>Reject WFH</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="alert('WFH request rejected!')">
                                                                <i class="feather-x-circle fs-12 text-danger"></i>
                                                                <span>Reject WFH</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.update-status'))
                                                            <form action="{{ route('hrms.wfh.update-status', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="pending">
                                                                <button type="submit" class="dropdown-item {{ $wfhStatus === 'pending' ? 'active-status' : '' }}">
                                                                    <i class="feather-clock fs-12 text-muted"></i>
                                                                    <span>Pending</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="alert('WFH set to pending!')">
                                                                <i class="feather-clock fs-12 text-muted"></i>
                                                                <span>Pending</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. PUNCHES TAB -->
            <div class="tab-pane fade" id="pills-punches" role="tabpanel" aria-labelledby="pills-punches-tab">
                <div class="table-responsive" style="overflow: visible;">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 18%;">TYPE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">PUNCH DATE & TIME</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 14%;">STATUS</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; width: 24%;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($correctionsList as $corr)
                                @php
                                    $corrDate = isset($corr->date) ? \Carbon\Carbon::parse($corr->date) : \Carbon\Carbon::today();
                                    $empName = is_object($corr->employee) ? ($corr->employee->full_name ?? 'Employee') : 'Employee';
                                    $empCode = is_object($corr->employee) ? ($corr->employee->employee_id ?? 'DT-0001') : 'DT-0001';
                                    $corrTypeName = $corr->type ?? 'Punch Correction';
                                    $corrTime = $corr->time ?? '09:30 AM';
                                    $corrId = is_object($corr) && isset($corr->id) ? $corr->id : null;
                                    $corrStatus = strtolower(is_object($corr) && isset($corr->status) ? $corr->status : 'pending');
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5">{{ $empCode }}</div>
                                    </td>

                                    <!-- CORRECTION TYPE -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate gap-1.5">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #f59e0b;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate">{{ $corrTypeName }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5 ps-2.5" title="{{ $corr->reason ?? '' }}">{{ $corr->reason ?? 'Attendance regularize' }}</div>
                                    </td>

                                    <!-- DATE & TIME -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-12 text-nowrap">
                                            {{ $corrDate->format('d M Y') }}
                                        </div>
                                        <div class="fs-11 text-muted mt-0.5">{{ $corrTime }}</div>
                                    </td>

                                    <!-- STATUS -->
                                    <td class="py-2.5 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        @if($corrStatus === 'approved')
                                            <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11 text-capitalize">Approved</span>
                                        @elseif($corrStatus === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11 text-capitalize">Rejected</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11 text-capitalize">Pending</span>
                                        @endif
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9; white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap" style="gap: 8px;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : '#' }}"
                                               class="btn btn-sm btn-soft-primary"
                                               title="View Details"
                                               style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                <i class="feather-eye fs-14"></i>
                                            </a>

                                            <div class="dropdown d-inline-block position-relative">
                                                <button class="btn btn-sm dropdown-toggle py-1 px-2.5 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Change Status">
                                                    <span>{{ ucfirst($corrStatus) }}</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu shadow" style="z-index: 1060;">
                                                    <li>
                                                        @if($corrId && \Illuminate\Support\Facades\Route::has('hrms.attendance.corrections.approve'))
                                                            <form action="{{ route('hrms.attendance.corrections.approve', $corrId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-success {{ $corrStatus === 'approved' ? 'active-status' : '' }}">
                                                                    <i class="feather-check-circle fs-12 text-success"></i>
                                                                    <span>Approve Punch</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-success" href="javascript:void(0)" onclick="alert('Punch regularization approved!')">
                                                                <i class="feather-check-circle fs-12 text-success"></i>
                                                                <span>Approve Punch</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($corrId && \Illuminate\Support\Facades\Route::has('hrms.attendance.corrections.reject'))
                                                            <form action="{{ route('hrms.attendance.corrections.reject', $corrId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-danger {{ $corrStatus === 'rejected' ? 'active-status' : '' }}">
                                                                    <i class="feather-x-circle fs-12 text-danger"></i>
                                                                    <span>Reject Punch</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="alert('Punch regularization rejected!')">
                                                                <i class="feather-x-circle fs-12 text-danger"></i>
                                                                <span>Reject Punch</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 4. EXPENSES TAB -->
            <div class="tab-pane fade" id="pills-expenses" role="tabpanel" aria-labelledby="pills-expenses-tab">
                <div class="table-responsive" style="overflow: visible;">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 18%;">CATEGORY</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 22%;">CLAIM AMOUNT</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; width: 14%;">STATUS</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; width: 24%;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expensesList as $exp)
                                @php
                                    $empName = is_object($exp->employee) ? ($exp->employee->full_name ?? 'Employee') : 'Employee';
                                    $empCode = is_object($exp->employee) ? ($exp->employee->employee_id ?? 'DT-0001') : 'DT-0001';
                                    $expCategory = $exp->category ?? 'Travel Expense';
                                    $expAmount = number_format($exp->total_amount ?? 0, 2);
                                    $expId = is_object($exp) && isset($exp->id) ? $exp->id : null;
                                    $expStatus = strtolower(is_object($exp) && isset($exp->status) ? $exp->status : 'pending');
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5">{{ $empCode }}</div>
                                    </td>

                                    <!-- CATEGORY -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate gap-1.5">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #10b981;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate">{{ $expCategory }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate mt-0.5 ps-2.5" title="{{ $exp->reason ?? '' }}">{{ $exp->reason ?? 'Claim request' }}</div>
                                    </td>

                                    <!-- CLAIM AMOUNT -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <span class="fw-bold text-dark fs-13 text-nowrap">
                                            ₹{{ $expAmount }}
                                        </span>
                                    </td>

                                    <!-- STATUS -->
                                    <td class="py-2.5 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        @if($expStatus === 'approved')
                                            <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11 text-capitalize">Approved</span>
                                        @elseif($expStatus === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11 text-capitalize">Rejected</span>
                                        @elseif($expStatus === 'paid')
                                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill fs-11 text-capitalize">Paid</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11 text-capitalize">Pending</span>
                                        @endif
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9; white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap" style="gap: 8px;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.travel-expense.index') ? route('hrms.travel-expense.index', ['tab' => 'report']) : '#' }}"
                                               class="btn btn-sm btn-soft-primary"
                                               title="View Details"
                                               style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                <i class="feather-eye fs-14"></i>
                                            </a>

                                            <div class="dropdown d-inline-block position-relative">
                                                <button class="btn btn-sm dropdown-toggle py-1 px-2.5 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Change Status">
                                                    <span>{{ ucfirst($expStatus) }}</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu shadow" style="z-index: 1060;">
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.approve'))
                                                            <form action="{{ route('hrms.travel-expense.report.approve', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-success {{ $expStatus === 'approved' ? 'active-status' : '' }}">
                                                                    <i class="feather-check-circle fs-12 text-success"></i>
                                                                    <span>Approve Expense</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-success" href="javascript:void(0)" onclick="alert('Expense report approved!')">
                                                                <i class="feather-check-circle fs-12 text-success"></i>
                                                                <span>Approve Expense</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.reject'))
                                                            <form action="{{ route('hrms.travel-expense.report.reject', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-danger {{ $expStatus === 'rejected' ? 'active-status' : '' }}">
                                                                    <i class="feather-x-circle fs-12 text-danger"></i>
                                                                    <span>Reject Expense</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="alert('Expense report rejected!')">
                                                                <i class="feather-x-circle fs-12 text-danger"></i>
                                                                <span>Reject Expense</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.pay'))
                                                            <form action="{{ route('hrms.travel-expense.report.pay', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-primary {{ $expStatus === 'paid' ? 'active-status' : '' }}">
                                                                    <i class="feather-dollar-sign fs-12 text-primary"></i>
                                                                    <span>Process Payout</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a class="dropdown-item text-primary" href="javascript:void(0)" onclick="alert('Expense payout processed!')">
                                                                <i class="feather-dollar-sign fs-12 text-primary"></i>
                                                                <span>Process Payout</span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
