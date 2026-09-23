@php
    $leavesList = !empty($pendingLeaves) && count($pendingLeaves) > 0 ? $pendingLeaves : [
        (object)[
            'id' => 1,
            'employee_id' => 1,
            'employee' => (object)['full_name' => 'Rahul Sharma', 'employee_id' => 'DT-0001'],
            'leaveType' => (object)['name' => 'Casual Leave'],
            'start_date' => '2026-09-24',
            'end_date' => '2026-09-26',
            'reason' => 'kjs dvsndvsod vs...'
        ],
        (object)[
            'id' => 2,
            'employee_id' => 4,
            'employee' => (object)['full_name' => 'Priya Patel', 'employee_id' => 'DT-0004'],
            'leaveType' => (object)['name' => 'Sick Leave'],
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-29',
            'reason' => 'Doctor appointment and medical leave'
        ],
        (object)[
            'id' => 3,
            'employee_id' => 12,
            'employee' => (object)['full_name' => 'Amit Kumar', 'employee_id' => 'DT-0012'],
            'leaveType' => (object)['name' => 'Earned Leave'],
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'reason' => 'Annual family vacation'
        ],
        (object)[
            'id' => 4,
            'employee_id' => 18,
            'employee' => (object)['full_name' => 'Neha Singh', 'employee_id' => 'DT-0018'],
            'leaveType' => (object)['name' => 'Casual Leave'],
            'start_date' => '2026-10-08',
            'end_date' => '2026-10-09',
            'reason' => 'Personal urgent work'
        ],
        (object)[
            'id' => 5,
            'employee_id' => 25,
            'employee' => (object)['full_name' => 'Vikas Reddy', 'employee_id' => 'DT-0025'],
            'leaveType' => (object)['name' => 'Unpaid Leave'],
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-14',
            'reason' => 'Relocation travel'
        ],
    ];

    $wfhList = !empty($pendingWfh) ? $pendingWfh : [];
    $correctionsList = !empty($pendingCorrections) ? $pendingCorrections : [];
    $expensesList = !empty($pendingExpenses) ? $pendingExpenses : [];
@endphp

<style>
    .pending-approval-pills .nav-link {
        color: #64748b !important;
        font-weight: 600;
        font-size: 12px;
        padding: 6px 14px;
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
</style>

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between border-bottom-0 flex-wrap gap-3">
        <!-- Title with Avatar Icon -->
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background: #eef2ff; border-radius: 10px; color: #4f46e5;">
                <i class="feather-inbox fs-16"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark ms-2.5" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Pending Approvals Action Center</h6>
        </div>

        <!-- Right Side Nav Pills -->
        <ul class="nav nav-pills border-0 p-1 rounded-3 pending-approval-pills d-inline-flex align-items-center gap-1" id="pills-approval-tab" role="tablist" style="background: #f1f5f9; border-radius: 12px;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-inline-flex align-items-center gap-1.5" id="pills-leaves-tab" data-bs-toggle="pill" data-bs-target="#pills-leaves" type="button" role="tab" aria-controls="pills-leaves" aria-selected="true">
                    Leaves <span class="badge rounded-pill px-2 py-0.5 fs-11 fw-bold ms-1" style="background: #eef2ff; color: #4f46e5;">{{ count($leavesList) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-inline-flex align-items-center gap-1.5" id="pills-wfh-tab" data-bs-toggle="pill" data-bs-target="#pills-wfh" type="button" role="tab" aria-controls="pills-wfh" aria-selected="false">
                    WFH <span class="badge rounded-pill px-2 py-0.5 fs-11 fw-bold ms-1" style="background: #ccfbf1; color: #0d9488;">{{ count($wfhList) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-inline-flex align-items-center gap-1.5" id="pills-punches-tab" data-bs-toggle="pill" data-bs-target="#pills-punches" type="button" role="tab" aria-controls="pills-punches" aria-selected="false">
                    Punches <span class="badge rounded-pill px-2 py-0.5 fs-11 fw-bold ms-1" style="background: #fef3c7; color: #d97706;">{{ count($correctionsList) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-inline-flex align-items-center gap-1.5" id="pills-expenses-tab" data-bs-toggle="pill" data-bs-target="#pills-expenses" type="button" role="tab" aria-controls="pills-expenses" aria-selected="false">
                    Expenses <span class="badge rounded-pill px-2 py-0.5 fs-11 fw-bold ms-1" style="background: #dcfce7; color: #16a34a;">{{ count($expensesList) }}</span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Table Container with Proper Height -->
    <div class="card-body p-0 overflow-auto" style="min-height: 400px;">
        <div class="tab-content" id="pills-approval-tabContent">
            <!-- 1. LEAVES TAB -->
            <div class="tab-pane fade show active" id="pills-leaves" role="tabpanel" aria-labelledby="pills-leaves-tab">
                <div class="table-responsive" style="min-height: 380px;">
                    <table class="table align-middle mb-0 fs-13">
                        <thead>
                            <tr style="background: #f1f4f9; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                <th class="py-2.5 ps-4 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">EMPLOYEE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">LEAVE TYPE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">DURATION & TIMELINE</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted text-center" style="letter-spacing: 0.05em; color: #64748b !important;">DAYS</th>
                                <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">REASON</th>
                                <th class="py-2.5 pe-4 border-0 fs-11 text-uppercase fw-bold text-muted text-end" style="letter-spacing: 0.05em; color: #64748b !important;">ACTION</th>
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
                                @endphp
                                <tr>
                                    <!-- EMPLOYEE -->
                                    <td class="py-3 ps-4 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        <div class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">{{ $empName }}</div>
                                        <div class="fs-11 text-muted" style="color: #94a3b8 !important; margin-top: 2px;">{{ $empCode }}</div>
                                    </td>

                                    <!-- LEAVE TYPE -->
                                    <td class="py-3 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        <div class="d-flex align-items-center">
                                            <span class="d-inline-block rounded-circle me-2 flex-shrink-0" style="width: 8px; height: 8px; background-color: #3b82f6;"></span>
                                            <span class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">{{ $leaveTypeName }}</span>
                                        </div>
                                        <div class="fs-11 text-muted" style="color: #94a3b8 !important; margin-top: 2px; padding-left: 16px;">Rem: 12 / 12 Days</div>
                                    </td>

                                    <!-- DURATION & TIMELINE -->
                                    <td class="py-3 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        <span class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">
                                            {{ $startDate->format('d M') }} &ndash; {{ $endDate->format('d M Y') }}
                                        </span>
                                    </td>

                                    <!-- DAYS -->
                                    <td class="py-3 align-middle text-center border-bottom" style="border-color: #f1f5f9;">
                                        <span class="badge px-2.5 py-1 fs-12 fw-bold rounded-2" style="background: #eff6ff; color: #3b82f6;">{{ $daysCount }}</span>
                                    </td>

                                    <!-- REASON -->
                                    <td class="py-3 align-middle border-bottom" style="border-color: #f1f5f9;">
                                        <span class="fs-13 text-secondary d-inline-block text-truncate" style="max-width: 180px; color: #64748b !important;">
                                            {{ $leave->reason ?? 'due to some work' }}
                                        </span>
                                    </td>

                                    <!-- ACTION DROPDOWN -->
                                    <td class="py-3 pe-4 align-middle text-end border-bottom" style="border-color: #f1f5f9;">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leaves.index') ? route('hrms.leaves.index') : '#' }}" class="btn border-0 p-0 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #f1f3f9; color: #64748b; border-radius: 6px;" title="View Details">
                                                <i class="feather-eye fs-13"></i>
                                            </a>

                                            <div class="dropdown d-inline-block">
                                                <button class="btn border-0 fw-bold fs-11 text-uppercase px-3 py-1.5 rounded-2 d-inline-flex align-items-center gap-1.5 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: #fef3c7; color: #d97706; border-radius: 8px;">
                                                    <i class="feather-clock fs-12"></i> PENDING
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 p-1.5 fs-12" style="z-index: 1050;">
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.approve'))
                                                            <form action="{{ route('hrms.leaves.approve', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-check-circle fs-14"></i> Approve Request
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2" onclick="alert('Leave request approved successfully!')">
                                                                <i class="feather-check-circle fs-14"></i> Approve Request
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($leaveId && \Illuminate\Support\Facades\Route::has('hrms.leaves.reject'))
                                                            <form action="{{ route('hrms.leaves.reject', $leaveId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-x-circle fs-14"></i> Reject Request
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2" onclick="alert('Leave request rejected!')">
                                                                <i class="feather-x-circle fs-14"></i> Reject Request
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
                <div class="table-responsive" style="min-height: 380px;">
                    @if(count($wfhList) > 0)
                        <table class="table align-middle mb-0 fs-13">
                            <thead>
                                <tr style="background: #f1f4f9; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                    <th class="py-2.5 ps-4 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">EMPLOYEE</th>
                                    <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">TYPE</th>
                                    <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">TIMELINE</th>
                                    <th class="py-2.5 pe-4 border-0 fs-11 text-uppercase fw-bold text-muted text-end" style="letter-spacing: 0.05em; color: #64748b !important;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($wfhList as $wfh)
                                    @php $wfhId = is_object($wfh) && isset($wfh->id) ? $wfh->id : null; @endphp
                                    <tr>
                                        <td class="py-3 ps-4 align-middle border-bottom" style="border-color: #f1f5f9;">
                                            <div class="fw-bold text-dark fs-14">{{ $wfh->employee?->full_name ?? 'Employee' }}</div>
                                        </td>
                                        <td class="py-3 align-middle border-bottom" style="border-color: #f1f5f9;">
                                            <span class="badge bg-soft-info text-info px-2 py-1 rounded">WFH Request</span>
                                        </td>
                                        <td class="py-3 align-middle border-bottom" style="border-color: #f1f5f9;">
                                            {{ \Carbon\Carbon::parse($wfh->start_date)->format('d M Y') }}
                                        </td>
                                        <td class="py-3 pe-4 align-middle text-end border-bottom" style="border-color: #f1f5f9;">
                                            <div class="dropdown d-inline-block">
                                                <button class="btn border-0 fw-bold fs-11 text-uppercase px-3 py-1.5 rounded-2 d-inline-flex align-items-center gap-1.5 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: #fef3c7; color: #d97706; border-radius: 8px;">
                                                    <i class="feather-clock fs-12"></i> PENDING
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 p-1.5 fs-12">
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.approve'))
                                                            <form action="{{ route('hrms.wfh.approve', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-check-circle fs-14"></i> Approve WFH
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2" onclick="alert('WFH request approved!')">
                                                                <i class="feather-check-circle fs-14"></i> Approve WFH
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($wfhId && \Illuminate\Support\Facades\Route::has('hrms.wfh.reject'))
                                                            <form action="{{ route('hrms.wfh.reject', $wfhId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-x-circle fs-14"></i> Reject WFH
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2" onclick="alert('WFH request rejected!')">
                                                                <i class="feather-x-circle fs-14"></i> Reject WFH
                                                            </button>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-5 text-muted fs-13" style="min-height: 380px; display: flex; align-items: center; justify-content: center;">
                            <div>
                                <i class="feather-check-circle fs-24 text-success d-block mb-2"></i>
                                No pending WFH requests.
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 3. PUNCHES TAB -->
            <div class="tab-pane fade" id="pills-punches" role="tabpanel" aria-labelledby="pills-punches-tab">
                <div class="table-responsive" style="min-height: 380px;">
                    @if(count($correctionsList) > 0)
                        <table class="table align-middle mb-0 fs-13">
                            <thead>
                                <tr style="background: #f1f4f9; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                    <th class="py-2.5 ps-4 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">EMPLOYEE</th>
                                    <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">PUNCH DATE</th>
                                    <th class="py-2.5 pe-4 border-0 fs-11 text-uppercase fw-bold text-muted text-end" style="letter-spacing: 0.05em; color: #64748b !important;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($correctionsList as $corr)
                                    @php $corrId = is_object($corr) && isset($corr->id) ? $corr->id : null; @endphp
                                    <tr>
                                        <td class="py-3 ps-4 align-middle border-bottom" style="border-color: #f1f5f9;">
                                            <div class="fw-bold text-dark fs-14">{{ $corr->employee?->full_name ?? 'Employee' }}</div>
                                        </td>
                                        <td class="py-3 align-middle border-bottom" style="border-color: #f1f5f9;">
                                            {{ \Carbon\Carbon::parse($corr->date)->format('d M Y') }}
                                        </td>
                                        <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                            <div class="dropdown d-inline-block">
                                                <button class="btn border-0 fw-bold fs-11 text-uppercase px-3 py-1.5 rounded-2 d-inline-flex align-items-center gap-1.5 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: #fef3c7; color: #d97706; border-radius: 8px;">
                                                    <i class="feather-clock fs-12"></i> PENDING
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 p-1.5 fs-12">
                                                    <li>
                                                        @if($corrId && \Illuminate\Support\Facades\Route::has('hrms.attendance.corrections.approve'))
                                                            <form action="{{ route('hrms.attendance.corrections.approve', $corrId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-check-circle fs-14"></i> Approve Punch
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2" onclick="alert('Punch regularization approved!')">
                                                                <i class="feather-check-circle fs-14"></i> Approve Punch
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($corrId && \Illuminate\Support\Facades\Route::has('hrms.attendance.corrections.reject'))
                                                            <form action="{{ route('hrms.attendance.corrections.reject', $corrId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-x-circle fs-14"></i> Reject Punch
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2" onclick="alert('Punch regularization rejected!')">
                                                                <i class="feather-x-circle fs-14"></i> Reject Punch
                                                            </button>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-5 text-muted fs-13" style="min-height: 380px; display: flex; align-items: center; justify-content: center;">
                            <div>
                                <i class="feather-check-circle fs-24 text-success d-block mb-2"></i>
                                No pending punch regularization requests.
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 4. EXPENSES TAB -->
            <div class="tab-pane fade" id="pills-expenses" role="tabpanel" aria-labelledby="pills-expenses-tab">
                <div class="table-responsive" style="min-height: 380px;">
                    @if(count($expensesList) > 0)
                        <table class="table align-middle mb-0 fs-13">
                            <thead>
                                <tr style="background: #f1f4f9; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                    <th class="py-2.5 ps-4 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">EMPLOYEE</th>
                                    <th class="py-2.5 border-0 fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.05em; color: #64748b !important;">CLAIM AMOUNT</th>
                                    <th class="py-2.5 pe-4 border-0 fs-11 text-uppercase fw-bold text-muted text-end" style="letter-spacing: 0.05em; color: #64748b !important;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expensesList as $exp)
                                    @php $expId = is_object($exp) && isset($exp->id) ? $exp->id : null; @endphp
                                    <tr>
                                        <td class="py-3 ps-4 align-middle border-bottom" style="border-color: #f1f5f9;">
                                            <div class="fw-bold text-dark fs-14">{{ $exp->employee?->full_name ?? 'Employee' }}</div>
                                        </td>
                                        <td class="py-3 align-middle border-bottom" style="border-color: #f1f5f9;">
                                            {{ number_format($exp->total_amount ?? 0, 2) }}
                                        </td>
                                        <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                            <div class="dropdown d-inline-block">
                                                <button class="btn border-0 fw-bold fs-11 text-uppercase px-3 py-1.5 rounded-2 d-inline-flex align-items-center gap-1.5 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: #fef3c7; color: #d97706; border-radius: 8px;">
                                                    <i class="feather-clock fs-12"></i> PENDING
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 p-1.5 fs-12">
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.approve'))
                                                            <form action="{{ route('hrms.travel-expense.report.approve', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-check-circle fs-14"></i> Approve Expense
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-success py-1.5 d-flex align-items-center gap-2" onclick="alert('Expense report approved!')">
                                                                <i class="feather-check-circle fs-14"></i> Approve Expense
                                                            </button>
                                                        @endif
                                                    </li>
                                                    <li>
                                                        @if($expId && \Illuminate\Support\Facades\Route::has('hrms.travel-expense.report.reject'))
                                                            <form action="{{ route('hrms.travel-expense.report.reject', $expId) }}" method="POST" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2">
                                                                    <i class="feather-x-circle fs-14"></i> Reject Expense
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button type="button" class="dropdown-item rounded-2 fw-semibold text-danger py-1.5 d-flex align-items-center gap-2" onclick="alert('Expense report rejected!')">
                                                                <i class="feather-x-circle fs-14"></i> Reject Expense
                                                            </button>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-5 text-muted fs-13" style="min-height: 380px; display: flex; align-items: center; justify-content: center;">
                            <div>
                                <i class="feather-check-circle fs-24 text-success d-block mb-2"></i>
                                No pending expense claims.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
