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
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .pending-approvals-widget .card-body {
        flex: 1;
        min-height: 0;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    .pending-approvals-widget .tab-content,
    .pending-approvals-widget .tab-pane {
        height: 100%;
    }
    .pending-approvals-widget .table-responsive {
        overflow-x: hidden !important;
        overflow-y: visible !important;
    }
    .pending-approvals-widget .table tbody tr {
        transition: background-color 0.15s ease;
    }
    .pending-approvals-widget .table tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* Common ERP Dropdown Menu */
    .approval-dropdown-menu {
        min-width: 190px !important;
        border-radius: 10px !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.05) !important;
        padding: 6px !important;
        background: #ffffff !important;
        z-index: 1060 !important;
    }
    .approval-dropdown-menu li {
        list-style: none;
        margin-bottom: 2px;
    }
    .approval-dropdown-menu li:last-child {
        margin-bottom: 0;
    }
    .approval-dropdown-item {
        width: 100%;
        border: none;
        background: transparent;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-align: left;
        transition: all 0.15s ease-in-out;
        color: #334155;
        cursor: pointer;
    }
    .approval-dropdown-item .item-icon {
        width: 22px;
        height: 22px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 11px;
        transition: all 0.15s ease-in-out;
    }
    .approval-dropdown-item .item-label {
        flex: 1;
        white-space: nowrap;
    }

    /* Approve */
    .approval-dropdown-item.item-approve {
        color: #15803d;
    }
    .approval-dropdown-item.item-approve .item-icon {
        background: #dcfce7;
        color: #16a34a;
    }
    .approval-dropdown-item.item-approve:hover {
        background: #f0fdf4;
        color: #166534;
    }
    .approval-dropdown-item.item-approve:hover .item-icon {
        background: #16a34a;
        color: #ffffff;
    }

    /* Reject */
    .approval-dropdown-item.item-reject {
        color: #b91c1c;
    }
    .approval-dropdown-item.item-reject .item-icon {
        background: #fee2e2;
        color: #dc2626;
    }
    .approval-dropdown-item.item-reject:hover {
        background: #fef2f2;
        color: #991b1b;
    }
    .approval-dropdown-item.item-reject:hover .item-icon {
        background: #dc2626;
        color: #ffffff;
    }

    /* Warning / Unauthorized */
    .approval-dropdown-item.item-warning {
        color: #c2410c;
    }
    .approval-dropdown-item.item-warning .item-icon {
        background: #ffedd5;
        color: #ea580c;
    }
    .approval-dropdown-item.item-warning:hover {
        background: #fff7ed;
        color: #9a3412;
    }
    .approval-dropdown-item.item-warning:hover .item-icon {
        background: #ea580c;
        color: #ffffff;
    }

    /* Purple / Unpaid */
    .approval-dropdown-item.item-purple {
        color: #7e22ce;
    }
    .approval-dropdown-item.item-purple .item-icon {
        background: #f3e8ff;
        color: #9333ea;
    }
    .approval-dropdown-item.item-purple:hover {
        background: #faf5ff;
        color: #6b21a8;
    }
    .approval-dropdown-item.item-purple:hover .item-icon {
        background: #9333ea;
        color: #ffffff;
    }

    /* Primary / Payout */
    .approval-dropdown-item.item-primary {
        color: #4338ca;
    }
    .approval-dropdown-item.item-primary .item-icon {
        background: #e0e7ff;
        color: #4f46e5;
    }
    .approval-dropdown-item.item-primary:hover {
        background: #eef2ff;
        color: #3730a3;
    }
    .approval-dropdown-item.item-primary:hover .item-icon {
        background: #4f46e5;
        color: #ffffff;
    }

    /* Pending / Neutral */
    .approval-dropdown-item.item-pending {
        color: #475569;
    }
    .approval-dropdown-item.item-pending .item-icon {
        background: #f1f5f9;
        color: #64748b;
    }
    .approval-dropdown-item.item-pending:hover {
        background: #f8fafc;
        color: #1e293b;
    }
    .approval-dropdown-item.item-pending:hover .item-icon {
        background: #64748b;
        color: #ffffff;
    }

    /* Common Status Badge & Action Controls */
    .status-badge-btn {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        padding: 4.5px 9px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid transparent;
        transition: all 0.15s ease;
        line-height: 1;
    }
    .status-badge-btn:hover {
        filter: brightness(0.96);
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .table-action-icon-btn {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        text-decoration: none;
    }
    .table-action-icon-btn:hover {
        background: #e2e8f0;
        color: #1e293b;
    }
</style>

<div class="card border-0 mb-0 h-100 shadow-sm pending-approvals-widget" style="border-radius: 16px; background: #ffffff; overflow: hidden;">
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

    <!-- Table Container with Single Overflow -->
    <div class="card-body p-0">
        <div class="tab-content" id="pills-approval-tabContent">
            <!-- 1. LEAVES TAB -->
            <div class="tab-pane fade show active" id="pills-leaves" role="tabpanel" aria-labelledby="pills-leaves-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 18%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 17%;">LEAVE TYPE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 23%;">DURATION & TIMELINE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-center text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 8%;">DAYS</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 15%;">REASON</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 19%;">ACTION</th>
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

                                    $badgeBg = match($leaveStatus) {
                                        'approved' => '#dcfce7',
                                        'rejected' => '#fee2e2',
                                        'unauthorized' => '#ffedd5',
                                        'unpaid' => '#f3e8ff',
                                        default => '#fef3c7',
                                    };
                                    $badgeColor = match($leaveStatus) {
                                        'approved' => '#15803d',
                                        'rejected' => '#b91c1c',
                                        'unauthorized' => '#c2410c',
                                        'unpaid' => '#7e22ce',
                                        default => '#d97706',
                                    };
                                    $badgeIcon = match($leaveStatus) {
                                        'approved' => 'feather-check-circle',
                                        'rejected' => 'feather-x-circle',
                                        'unauthorized' => 'feather-alert-triangle',
                                        'unpaid' => 'feather-dollar-sign',
                                        default => 'feather-clock',
                                    };
                                    $isDropup = $loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1);
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px;">{{ $empCode }}</div>
                                    </td>

                                    <!-- LEAVE TYPE -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate" style="gap: 5px;">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #3b82f6;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $leaveTypeName }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px; padding-left: 11px;">Rem: 12 / 12 Days</div>
                                    </td>

                                    <!-- DURATION & TIMELINE -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <span class="fw-bold text-dark fs-12 text-nowrap" style="color: #1e293b !important; font-weight: 700;">
                                            {{ $startDate->format('d M') }} &ndash; {{ $endDate->format('d M Y') }}
                                        </span>
                                    </td>

                                    <!-- DAYS -->
                                    <td class="py-2.5 align-middle text-center border-bottom" style="border-color: #f1f5f9;">
                                        <span class="badge px-1.5 py-0.5 fs-10 fw-bold rounded-2" style="background: #eff6ff; color: #3b82f6;">{{ $daysCount }}</span>
                                    </td>

                                    <!-- REASON -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <span class="fs-12 text-secondary d-block text-truncate" style="max-width: 100%; color: #64748b !important;" title="{{ $leave->reason ?? '' }}">
                                            {{ $leave->reason ?? 'due to some work' }}
                                        </span>
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9;">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap" style="max-width: 100%;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leaves.index') ? route('hrms.leaves.index') : '#' }}" class="table-action-icon-btn" title="View Details">
                                                <i class="feather-eye fs-12"></i>
                                            </a>

                                            <div class="dropdown {{ $isDropup ? 'dropup' : '' }} d-inline-block flex-shrink-0 position-relative">
                                                <button class="status-badge-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="background: {{ $badgeBg }}; color: {{ $badgeColor }};">
                                                    <i class="{{ $badgeIcon }} fs-11"></i> {{ strtoupper($leaveStatus) }}
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end approval-dropdown-menu shadow">
                                                    <!-- 1. Approve Request -->
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="approved">
                                                                <button type="submit" class="approval-dropdown-item item-approve">
                                                                    <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                    <span class="item-label">Approve Request</span>
                                                                </button>
                                                            </form>
                                                        @elseif($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.approve'))
                                                            <form action="{{ route('hrms.leaves.approve', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-approve">
                                                                    <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                    <span class="item-label">Approve Request</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-approve" onclick="alert('Leave request approved successfully!')">
                                                                <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                <span class="item-label">Approve Request</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- 2. Reject Request -->
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="rejected">
                                                                <button type="submit" class="approval-dropdown-item item-reject">
                                                                    <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                    <span class="item-label">Reject Request</span>
                                                                </button>
                                                            </form>
                                                        @elseif($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.reject'))
                                                            <form action="{{ route('hrms.leaves.reject', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-reject">
                                                                    <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                    <span class="item-label">Reject Request</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-reject" onclick="alert('Leave request rejected!')">
                                                                <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                <span class="item-label">Reject Request</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- 3. Mark as Unauthorized -->
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="unauthorized">
                                                                <button type="submit" class="approval-dropdown-item item-warning">
                                                                    <span class="item-icon"><i class="feather-alert-triangle"></i></span>
                                                                    <span class="item-label">Mark Unauthorized</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-warning" onclick="alert('Marked as unauthorized!')">
                                                                <span class="item-icon"><i class="feather-alert-triangle"></i></span>
                                                                <span class="item-label">Mark Unauthorized</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- 4. Mark as Unpaid -->
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="unpaid">
                                                                <button type="submit" class="approval-dropdown-item item-purple">
                                                                    <span class="item-icon"><i class="feather-dollar-sign"></i></span>
                                                                    <span class="item-label">Mark as Unpaid</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-purple" onclick="alert('Marked as unpaid!')">
                                                                <span class="item-icon"><i class="feather-dollar-sign"></i></span>
                                                                <span class="item-label">Mark as Unpaid</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- 5. Set to Pending -->
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.update-status'))
                                                            <form action="{{ route('hrms.leaves.update-status', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="pending">
                                                                <button type="submit" class="approval-dropdown-item item-pending">
                                                                    <span class="item-icon"><i class="feather-clock"></i></span>
                                                                    <span class="item-label">Set to Pending</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-pending" onclick="alert('Status reset to pending!')">
                                                                <span class="item-icon"><i class="feather-clock"></i></span>
                                                                <span class="item-label">Set to Pending</span>
                                                            </button>
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
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 18%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 17%;">REQUEST TYPE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 23%;">DURATION & TIMELINE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-center text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 8%;">DAYS</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 15%;">REASON</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 19%;">ACTION</th>
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

                                    $badgeBg = match($wfhStatus) {
                                        'approved' => '#dcfce7',
                                        'rejected' => '#fee2e2',
                                        default => '#fef3c7',
                                    };
                                    $badgeColor = match($wfhStatus) {
                                        'approved' => '#15803d',
                                        'rejected' => '#b91c1c',
                                        default => '#d97706',
                                    };
                                    $badgeIcon = match($wfhStatus) {
                                        'approved' => 'feather-check-circle',
                                        'rejected' => 'feather-x-circle',
                                        default => 'feather-clock',
                                    };
                                    $isDropup = $loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1);
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px;">{{ $empCode }}</div>
                                    </td>

                                    <!-- REQUEST TYPE -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate" style="gap: 5px;">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #0d9488;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $wfhTypeName }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px; padding-left: 11px;">Remote Office</div>
                                    </td>

                                    <!-- DURATION & TIMELINE -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <span class="fw-bold text-dark fs-12 text-nowrap" style="color: #1e293b !important; font-weight: 700;">
                                            {{ $wfhStart->format('d M') }} &ndash; {{ $wfhEnd->format('d M Y') }}
                                        </span>
                                    </td>

                                    <!-- DAYS -->
                                    <td class="py-2.5 align-middle text-center border-bottom" style="border-color: #f1f5f9;">
                                        <span class="badge px-1.5 py-0.5 fs-10 fw-bold rounded-2" style="background: #ccfbf1; color: #0d9488;">{{ $wfhDays }}</span>
                                    </td>

                                    <!-- REASON -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <span class="fs-12 text-secondary d-block text-truncate" style="max-width: 100%; color: #64748b !important;" title="{{ $wfh->reason ?? '' }}">
                                            {{ $wfh->reason ?? 'WFH required' }}
                                        </span>
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9;">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap" style="max-width: 100%;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.wfh.index') ? route('hrms.wfh.index') : '#' }}" class="table-action-icon-btn" title="View WFH Applications">
                                                <i class="feather-eye fs-12"></i>
                                            </a>

                                            <div class="dropdown {{ $isDropup ? 'dropup' : '' }} d-inline-block flex-shrink-0 position-relative">
                                                <button class="status-badge-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="background: {{ $badgeBg }}; color: {{ $badgeColor }};">
                                                    <i class="{{ $badgeIcon }} fs-11"></i> {{ strtoupper($wfhStatus) }}
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end approval-dropdown-menu shadow">
                                                    <!-- Approve WFH -->
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.update-status'))
                                                            <form action="{{ route('hrms.wfh.update-status', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="approved">
                                                                <button type="submit" class="approval-dropdown-item item-approve">
                                                                    <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                    <span class="item-label">Approve WFH</span>
                                                                </button>
                                                            </form>
                                                        @elseif($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.approve'))
                                                            <form action="{{ route('hrms.wfh.approve', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-approve">
                                                                    <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                    <span class="item-label">Approve WFH</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-approve" onclick="alert('WFH request approved!')">
                                                                <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                <span class="item-label">Approve WFH</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- Reject WFH -->
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.update-status'))
                                                            <form action="{{ route('hrms.wfh.update-status', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="rejected">
                                                                <button type="submit" class="approval-dropdown-item item-reject">
                                                                    <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                    <span class="item-label">Reject WFH</span>
                                                                </button>
                                                            </form>
                                                        @elseif($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.reject'))
                                                            <form action="{{ route('hrms.wfh.reject', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-reject">
                                                                    <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                    <span class="item-label">Reject WFH</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-reject" onclick="alert('WFH request rejected!')">
                                                                <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                <span class="item-label">Reject WFH</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- Set to Pending -->
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.update-status'))
                                                            <form action="{{ route('hrms.wfh.update-status', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <input type="hidden" name="action" value="pending">
                                                                <button type="submit" class="approval-dropdown-item item-pending">
                                                                    <span class="item-icon"><i class="feather-clock"></i></span>
                                                                    <span class="item-label">Set to Pending</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-pending" onclick="alert('WFH set to pending!')">
                                                                <span class="item-icon"><i class="feather-clock"></i></span>
                                                                <span class="item-label">Set to Pending</span>
                                                            </button>
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
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 20%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 18%;">TYPE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 22%;">PUNCH DATE & TIME</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 21%;">REASON / NOTE</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 19%;">ACTION</th>
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

                                    $badgeBg = match($corrStatus) {
                                        'approved' => '#dcfce7',
                                        'rejected' => '#fee2e2',
                                        default => '#fef3c7',
                                    };
                                    $badgeColor = match($corrStatus) {
                                        'approved' => '#15803d',
                                        'rejected' => '#b91c1c',
                                        default => '#d97706',
                                    };
                                    $badgeIcon = match($corrStatus) {
                                        'approved' => 'feather-check-circle',
                                        'rejected' => 'feather-x-circle',
                                        default => 'feather-clock',
                                    };
                                    $isDropup = $loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1);
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px;">{{ $empCode }}</div>
                                    </td>

                                    <!-- CORRECTION TYPE -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate" style="gap: 5px;">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #f59e0b;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $corrTypeName }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px; padding-left: 11px;">Attendance Fix</div>
                                    </td>

                                    <!-- DATE & TIME -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <span class="fw-bold text-dark fs-12 text-nowrap" style="color: #1e293b !important; font-weight: 700;">
                                            {{ $corrDate->format('d M Y') }}
                                        </span>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px;">{{ $corrTime }}</div>
                                    </td>

                                    <!-- REASON -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <span class="fs-12 text-secondary d-block text-truncate" style="max-width: 100%; color: #64748b !important;" title="{{ $corr->reason ?? '' }}">
                                            {{ $corr->reason ?? 'Punch regularization requested' }}
                                        </span>
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9;">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap" style="max-width: 100%;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : '#' }}" class="table-action-icon-btn" title="View Attendance Logs">
                                                <i class="feather-eye fs-12"></i>
                                            </a>

                                            <div class="dropdown {{ $isDropup ? 'dropup' : '' }} d-inline-block flex-shrink-0 position-relative">
                                                <button class="status-badge-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="background: {{ $badgeBg }}; color: {{ $badgeColor }};">
                                                    <i class="{{ $badgeIcon }} fs-11"></i> {{ strtoupper($corrStatus) }}
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end approval-dropdown-menu shadow">
                                                    <!-- Approve Punch -->
                                                    <li>
                                                        @if($corrId && \Illuminate\Support\Facades\Route::has('hrms.attendance.corrections.approve'))
                                                            <form action="{{ route('hrms.attendance.corrections.approve', $corrId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-approve">
                                                                    <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                    <span class="item-label">Approve Punch</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-approve" onclick="alert('Punch regularization approved!')">
                                                                <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                <span class="item-label">Approve Punch</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- Reject Punch -->
                                                    <li>
                                                        @if($corrId && \Illuminate\Support\Facades\Route::has('hrms.attendance.corrections.reject'))
                                                            <form action="{{ route('hrms.attendance.corrections.reject', $corrId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-reject">
                                                                    <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                    <span class="item-label">Reject Punch</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-reject" onclick="alert('Punch regularization rejected!')">
                                                                <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                <span class="item-label">Reject Punch</span>
                                                            </button>
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
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-3 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 20%;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 18%;">CATEGORY</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 18%;">CLAIM AMOUNT</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 25%;">PURPOSE / REASON</th>
                                <th class="py-2.5 pe-3 border-0 fs-11 text-uppercase fw-bold text-muted text-end text-nowrap" style="letter-spacing: 0.04em; color: #64748b !important; width: 19%;">ACTION</th>
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

                                    $badgeBg = match($expStatus) {
                                        'approved' => '#dcfce7',
                                        'rejected' => '#fee2e2',
                                        'paid' => '#e0e7ff',
                                        default => '#fef3c7',
                                    };
                                    $badgeColor = match($expStatus) {
                                        'approved' => '#15803d',
                                        'rejected' => '#b91c1c',
                                        'paid' => '#4338ca',
                                        default => '#d97706',
                                    };
                                    $badgeIcon = match($expStatus) {
                                        'approved' => 'feather-check-circle',
                                        'rejected' => 'feather-x-circle',
                                        'paid' => 'feather-dollar-sign',
                                        default => 'feather-clock',
                                    };
                                    $isDropup = $loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1);
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-2.5 ps-3 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $empName }}</div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px;">{{ $empCode }}</div>
                                    </td>

                                    <!-- CATEGORY -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center text-truncate" style="gap: 5px;">
                                            <span class="d-inline-block rounded-circle me-1 flex-shrink-0" style="width: 6px; height: 6px; background-color: #10b981;"></span>
                                            <span class="fw-bold text-dark fs-13 text-truncate" style="color: #1e293b !important; font-weight: 700;">{{ $expCategory }}</span>
                                        </div>
                                        <div class="fs-11 text-muted text-truncate" style="color: #94a3b8 !important; margin-top: 1px; padding-left: 11px;">Reimbursement</div>
                                    </td>

                                    <!-- CLAIM AMOUNT -->
                                    <td class="py-2.5 align-middle border-bottom text-nowrap" style="border-color: #f1f5f9;">
                                        <span class="fw-bold text-dark fs-13 text-nowrap" style="color: #1e293b !important; font-weight: 700;">
                                            ₹{{ $expAmount }}
                                        </span>
                                    </td>

                                    <!-- REASON -->
                                    <td class="py-2.5 align-middle border-bottom text-truncate" style="border-color: #f1f5f9;">
                                        <span class="fs-12 text-secondary d-block text-truncate" style="max-width: 100%; color: #64748b !important;" title="{{ $exp->reason ?? '' }}">
                                            {{ $exp->reason ?? 'Official expense claim' }}
                                        </span>
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-2.5 pe-3 align-middle text-end border-bottom" style="border-color: #f1f5f9;">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap" style="max-width: 100%;">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.travel-expense.index') ? route('hrms.travel-expense.index', ['tab' => 'report']) : '#' }}" class="table-action-icon-btn" title="View Expense Reports">
                                                <i class="feather-eye fs-12"></i>
                                            </a>

                                            <div class="dropdown {{ $isDropup ? 'dropup' : '' }} d-inline-block flex-shrink-0 position-relative">
                                                <button class="status-badge-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="background: {{ $badgeBg }}; color: {{ $badgeColor }};">
                                                    <i class="{{ $badgeIcon }} fs-11"></i> {{ strtoupper($expStatus) }}
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end approval-dropdown-menu shadow">
                                                    <!-- Approve Expense -->
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.approve'))
                                                            <form action="{{ route('hrms.travel-expense.report.approve', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-approve">
                                                                    <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                    <span class="item-label">Approve Expense</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-approve" onclick="alert('Expense report approved!')">
                                                                <span class="item-icon"><i class="feather-check-circle"></i></span>
                                                                <span class="item-label">Approve Expense</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- Reject Expense -->
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.reject'))
                                                            <form action="{{ route('hrms.travel-expense.report.reject', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-reject">
                                                                    <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                    <span class="item-label">Reject Expense</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-reject" onclick="alert('Expense report rejected!')">
                                                                <span class="item-icon"><i class="feather-x-circle"></i></span>
                                                                <span class="item-label">Reject Expense</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <!-- Process Payout -->
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.pay'))
                                                            <form action="{{ route('hrms.travel-expense.report.pay', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="approval-dropdown-item item-primary">
                                                                    <span class="item-icon"><i class="feather-dollar-sign"></i></span>
                                                                    <span class="item-label">Process Payout</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="approval-dropdown-item item-primary" onclick="alert('Expense payout processed!')">
                                                                <span class="item-icon"><i class="feather-dollar-sign"></i></span>
                                                                <span class="item-label">Process Payout</span>
                                                            </button>
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
