<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\HolidayCalendar;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\WfhRequest;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrmsDashboardController extends Controller
{
    public function __construct(
        private readonly \App\Domains\Platform\Services\DashboardService $layouts,
    ) {
    }
    /**
     * Display the comprehensive Role-Ready HRMS Dashboard.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now();

        // 1. Resolve current logged-in employee context
        $currentEmployee = $this->resolveCurrentEmployee(auth()->user(), $tenantId);

        // 2. Workforce Overview Metrics
        $totalEmployees = Employee::where('tenant_id', $tenantId)->count();
        $probationCount = Employee::where('tenant_id', $tenantId)->where('employee_stage', 'Probation')->count();
        $confirmedCount = Employee::where('tenant_id', $tenantId)->where('employee_stage', 'Confirmed')->count();
        $noticeCount = Employee::where('tenant_id', $tenantId)->whereIn('employee_stage', ['Notice Period', 'Serving Notice'])->count();

        // New Joinees (Joined in last 30 days)
        $newHiresList = Employee::with(['department', 'designation'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('date_of_joining')
            ->whereDate('date_of_joining', '>=', $now->copy()->subDays(30))
            ->whereDate('date_of_joining', '<=', $today)
            ->orderBy('date_of_joining', 'desc')
            ->take(6)
            ->get();
        $newHiresThisMonth = $newHiresList->count();

        // 3. Today's Real-Time Attendance Pulse
        $todayAttendances = Attendance::where('tenant_id', $tenantId)
            ->whereDate('date', $today)
            ->get();
        $presentCount = $todayAttendances->whereIn('status', ['present', 'late', 'half_day'])->count();
        $lateCount = $todayAttendances->where('status', 'late')->count();

        $wfhCount = WfhRequest::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count() + $todayAttendances->where('location_type', 'wfh')->count();

        $onLeaveCount = LeaveRequest::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        $attendancePercent = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100, 1) : 0;

        // 4. Current Employee Self-Service (ESS) & Web Punch Data
        $myTodayAttendance = null;
        $recentPunches = [];
        $profileCompletion = 85;
        $myLeaveBalances = [
            'casual' => ['allocated' => 12, 'used' => 0, 'remaining' => 12],
            'sick' => ['allocated' => 8, 'used' => 0, 'remaining' => 8],
            'earned' => ['allocated' => 15, 'used' => 0, 'remaining' => 15],
        ];

        if ($currentEmployee) {
            $myTodayAttendance = Attendance::with('breaks')
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id)
                ->whereDate('date', $today)
                ->first();

            // 7-day recent punch history (for 7-day visual mini-strip)
            for ($i = 6; $i >= 0; $i--) {
                $pastDate = $now->copy()->subDays($i);
                $pastDateStr = $pastDate->format('Y-m-d');
                $isWeekend = $pastDate->isWeekend();

                $att = Attendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $currentEmployee->id)
                    ->whereDate('date', $pastDateStr)
                    ->first();

                $status = 'unmarked';
                if ($att) {
                    $status = $att->status ?: 'present';
                } elseif ($isWeekend) {
                    $status = 'off';
                } elseif ($pastDate->isPast() && !$pastDate->isToday()) {
                    $status = 'absent';
                }

                $recentPunches[] = [
                    'day_name' => $pastDate->format('D'),
                    'day_num' => $pastDate->format('d'),
                    'date' => $pastDateStr,
                    'status' => $status,
                    'check_in' => $att?->check_in ? Carbon::parse($att->check_in)->format('h:i A') : null,
                    'check_out' => $att?->check_out ? Carbon::parse($att->check_out)->format('h:i A') : null,
                    'is_today' => $pastDate->isToday(),
                ];
            }

            // Real Leave Balances from DB
            $dbBalances = LeaveBalance::with('leaveType')
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id)
                ->get();

            if ($dbBalances->isNotEmpty()) {
                foreach ($dbBalances as $bal) {
                    $typeName = strtolower($bal->leaveType->name ?? $bal->leaveType->code ?? '');
                    $key = null;
                    if (str_contains($typeName, 'casual') || str_contains($typeName, 'cl')) {
                        $key = 'casual';
                    } elseif (str_contains($typeName, 'sick') || str_contains($typeName, 'sl')) {
                        $key = 'sick';
                    } elseif (str_contains($typeName, 'earned') || str_contains($typeName, 'el') || str_contains($typeName, 'privilege')) {
                        $key = 'earned';
                    }

                    if ($key) {
                        $myLeaveBalances[$key] = [
                            'allocated' => floatval($bal->allocated),
                            'used' => floatval($bal->used),
                            'remaining' => floatval($bal->remaining),
                        ];
                    }
                }
            }

            // Calculate Profile & KYC Completion %
            $fields = [
                $currentEmployee->full_name,
                $currentEmployee->office_email,
                $currentEmployee->phone_number,
                $currentEmployee->date_of_birth,
                $currentEmployee->pan_card_number,
                $currentEmployee->aadhaar_card_number,
                $currentEmployee->bank_name,
                $currentEmployee->account_number,
                $currentEmployee->ifsc_code,
                $currentEmployee->emergency_contact_phone ?? $currentEmployee->phone_number,
            ];
            $filled = count(array_filter($fields));
            $profileCompletion = count($fields) > 0 ? round(($filled / count($fields)) * 100) : 100;
        }

        // Resolve Assigned Leave Plan & Detailed Leave Types List
        $myAssignedPlan = null;
        if ($currentEmployee && $currentEmployee->leave_plan_id) {
            $myAssignedPlan = LeavePlan::with('types')->find($currentEmployee->leave_plan_id);
        }
        if (!$myAssignedPlan) {
            $myAssignedPlan = LeavePlan::where('tenant_id', $tenantId)->where('status', true)->with('types')->first();
        }

        $myLeaveTypesList = [];
        $planTypesCollection = collect();
        if ($myAssignedPlan && $myAssignedPlan->types->isNotEmpty()) {
            $planTypesCollection = $myAssignedPlan->types;
        } else {
            $planTypesCollection = LeaveType::where('tenant_id', $tenantId)->where('status', true)->get();
        }

        foreach ($planTypesCollection as $type) {
            $bal = null;
            if ($currentEmployee) {
                $bal = LeaveBalance::where('tenant_id', $tenantId)
                    ->where('employee_id', $currentEmployee->id)
                    ->where('leave_type_id', $type->id)
                    ->first();
            }

            $allocated = $bal ? floatval($bal->allocated) : floatval($type->quota ?? 12);
            $used = $bal ? floatval($bal->used) : 0;
            $remaining = $bal ? floatval($bal->remaining) : $allocated;

            $myLeaveTypesList[] = [
                'id'          => $type->id,
                'name'        => $type->name,
                'code'        => $type->code ?: strtoupper(substr($type->name, 0, 2)),
                'color'       => $type->color ?: '#3b82f6',
                'quota'       => floatval($type->quota ?? 12),
                'allocated'   => $allocated,
                'used'        => $used,
                'remaining'   => $remaining,
                'rules'       => $type->rules ?: [],
                'description' => $type->description ?: '',
            ];
        }

        // Resolve Shift Details & Weekly Roster Pattern dynamically from Master DB
        $activeShift = null;
        if ($currentEmployee) {
            $activeShift = $currentEmployee->resolveShiftForDate($today);
            if (!$activeShift && $currentEmployee->shift_id) {
                $activeShift = \App\Domains\Production\Models\ProductionShift::find($currentEmployee->shift_id);
            }
        }

        if (!$activeShift) {
            $activeShift = \App\Domains\Production\Models\ProductionShift::where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                }
            })->where('active', true)->first();
        }

        if (!$activeShift) {
            $activeShift = \App\Domains\Production\Models\ProductionShift::first();
        }

        if ($activeShift) {
            $startTime = $activeShift->start_time ? Carbon::parse($activeShift->start_time)->format('h:i A') : '09:00 AM';
            $endTime   = $activeShift->end_time ? Carbon::parse($activeShift->end_time)->format('h:i A') : '06:00 PM';
            $myShiftDetails = [
                'name'            => $activeShift->name ?: 'Day Shift',
                'badge'           => $activeShift->code ?: 'Default',
                'timing'          => $startTime . ' - ' . $endTime,
                'overtime_status' => $activeShift->overtime_allowed ? 'Allowed' : 'Not Allowed',
                'is_ot_allowed'   => (bool) $activeShift->overtime_allowed,
            ];
        } else {
            $myShiftDetails = [
                'name'            => 'Day Shift',
                'badge'           => 'Default',
                'timing'          => '09:00 AM - 06:00 PM',
                'overtime_status' => 'Not Allowed',
                'is_ot_allowed'   => false,
            ];
        }

        $myWeeklyPattern = [];
        $startOfWeek = $now->copy()->startOfWeek(Carbon::SUNDAY);
        for ($i = 0; $i < 7; $i++) {
            $dayDate = $startOfWeek->copy()->addDays($i);
            $dayDateStr = $dayDate->format('Y-m-d');
            $dayName = $dayDate->format('D');

            $dayShift = null;
            if ($currentEmployee) {
                $dayShift = $currentEmployee->resolveShiftForDate($dayDateStr);
            }

            if ($dayShift) {
                $myWeeklyPattern[] = [
                    'day'    => $dayName,
                    'status' => $dayShift->name,
                    'is_off' => false,
                ];
            } else {
                $isWeekend = ($dayDate->dayOfWeek === 0);
                if ($isWeekend) {
                    $myWeeklyPattern[] = [
                        'day'    => $dayName,
                        'status' => 'Day Off',
                        'is_off' => true,
                    ];
                } else {
                    $myWeeklyPattern[] = [
                        'day'    => $dayName,
                        'status' => $activeShift ? $activeShift->name : 'Day Shift',
                        'is_off' => false,
                    ];
                }
            }
        }

        // 5. Unified Action Center / Pending Inboxes (Leaves, WFH, Regularizations, Expenses)
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
        $user = auth()->user();

        $pendingLeavesQuery = LeaveRequest::with(['employee.department', 'employee.designation', 'leaveType'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingLeavesQuery, $user);
        $pendingLeaves = $pendingLeavesQuery->latest()->take(20)->get();

        $pendingWfhQuery = WfhRequest::with(['employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingWfhQuery, $user);
        $pendingWfh = $pendingWfhQuery->latest()->take(20)->get();

        $pendingCorrectionsQuery = AttendanceCorrection::with(['employee.department', 'attendance'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingCorrectionsQuery, $user);
        $pendingCorrections = $pendingCorrectionsQuery->latest()->take(20)->get();

        $pendingExpensesQuery = ExpenseReport::with(['employee.department'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingExpensesQuery, $user);
        $pendingExpenses = $pendingExpensesQuery->latest()->take(20)->get();

        $totalPendingApprovals = $pendingLeaves->count() + $pendingWfh->count() + $pendingCorrections->count() + $pendingExpenses->count();

        // 6. Probation Watch
        $upcomingProbationEmployees = Employee::with(['department', 'designation', 'reportingManager'])
            ->where('tenant_id', $tenantId)
            ->where('employee_stage', 'Probation')
            ->whereNotNull('probation_end_date')
            ->orderBy('probation_end_date', 'asc')
            ->take(15)
            ->get();

        // 7. Offboarding & Active Exits Pipeline
        $activeExits = EmployeeExit::with(['employee.department', 'employee.designation', 'clearances'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['initiated', 'in_clearance', 'approved'])
            ->latest()
            ->take(15)
            ->get();

        // 8. Upcoming Holidays
        $upcomingHolidays = HolidayCalendar::where('tenant_id', $tenantId)
            ->where('status', true)
            ->whereDate('holiday_date', '>=', $today)
            ->orderBy('holiday_date', 'asc')
            ->take(15)
            ->get();

        // 9. Celebrations (Birthdays & Work Anniversaries from real active employees for current month)
        $allActiveEmployees = Employee::with(['department', 'designation'])
            ->where('tenant_id', $tenantId)
            ->where('status', true)
            ->get();

        $upcomingBirthdays = $allActiveEmployees->filter(function ($emp) use ($now) {
            if (!$emp->date_of_birth) return false;
            $bday = Carbon::parse($emp->date_of_birth);
            return $bday->month === $now->month;
        })->sortBy(function ($emp) {
            return Carbon::parse($emp->date_of_birth)->day;
        })->values();

        $upcomingAnniversaries = $allActiveEmployees->filter(function ($emp) use ($now) {
            if (!$emp->date_of_joining) return false;
            $doj = Carbon::parse($emp->date_of_joining);
            return $doj->month === $now->month && $doj->year < $now->year;
        })->sortBy(function ($emp) {
            return Carbon::parse($emp->date_of_joining)->day;
        })->values();

        // 10. Department Distribution Breakdown
        $departments = Department::where('tenant_id', $tenantId)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->take(6)
            ->get();

        $user = auth()->user();
        $isHrOrAdmin = $user && (
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.leave_requests.approve') ||
            $user->hasHrPermission('hrms.roster.manage') ||
            $user->hasHrPermission('hrms.shift_roster.manage') ||
            $user->hasHrPermission('hrms.travel_expenses.approve') ||
            $user->hasHrPermission('hrms.attendance.view')
        );

        // 11. Late Arrivals (Last 7 Days) & Unprocessed Penalties
        $lateArrivalsQuery = Attendance::with(['employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['late', 'half_day'])
            ->whereDate('date', '>=', $now->copy()->subDays(7));
        $scopeService->applyRelatedScope($lateArrivalsQuery, $user);

        $recentLateArrivals = $lateArrivalsQuery
            ->orderBy('date', 'desc')
            ->take(15)
            ->get();

        $penaltiesQuery = EmployeePenalty::with(['employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', 'pending')
                  ->orWhere('status', 'unprocessed');
            });
        $scopeService->applyRelatedScope($penaltiesQuery, $user);

        $unprocessedPenalties = $penaltiesQuery
            ->orderBy('date', 'desc')
            ->take(15)
            ->get();

        // 12. Leave Types & Approved Leaves
        $leaveTypes = LeaveType::where('tenant_id', $tenantId)->where('status', true)->get();
        if ($leaveTypes->isEmpty()) {
            $leaveTypes = LeaveType::where('tenant_id', $tenantId)->get();
        }

        $approvedLeavesQuery = LeaveRequest::with(['employee.department', 'employee.designation', 'leaveType'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $today);
        $scopeService->applyRelatedScope($approvedLeavesQuery, $user);

        $approvedLeaves = $approvedLeavesQuery
            ->orderBy('start_date', 'asc')
            ->take(15)
            ->get();

        // 13. Active Company Broadcasts & Announcements
        app(\App\Domains\HRMS\Services\BroadcastService::class)->processScheduledBroadcasts($tenantId);

        $totalBroadcastsCount = Broadcast::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->count();

        $allPublishedBroadcasts = Broadcast::with(['creator', 'receipts', 'comments.employee'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->get();

        $latestBroadcasts = $allPublishedBroadcasts->sort(function ($a, $b) use ($currentEmployee) {
            $aAckReq = $a->is_acknowledgement_required;
            $bAckReq = $b->is_acknowledgement_required;

            $aReceipt = $currentEmployee ? $a->receipts->where('employee_id', $currentEmployee->id)->first() : null;
            $bReceipt = $currentEmployee ? $b->receipts->where('employee_id', $currentEmployee->id)->first() : null;

            $aPending = $aAckReq && (!$aReceipt || !$aReceipt->acknowledged_at);
            $bPending = $bAckReq && (!$bReceipt || !$bReceipt->acknowledged_at);

            // 1. Pending unacknowledged compliance items come first
            if ($aPending !== $bPending) {
                return $aPending ? -1 : 1;
            }

            // 2. Priority order (urgent > important > normal)
            $priorities = ['urgent' => 1, 'important' => 2, 'normal' => 3];
            $aPrio = $priorities[$a->priority] ?? 3;
            $bPrio = $priorities[$b->priority] ?? 3;
            if ($aPrio !== $bPrio) {
                return $aPrio <=> $bPrio;
            }

            // 3. Newest published date
            return strtotime($b->published_at ?? $b->created_at) <=> strtotime($a->published_at ?? $a->created_at);
        })->take(3)->values();

        $user = auth()->user();
        $isHrOrAdmin = $user && (
            app(\App\Services\Access\AccessService::class)->allows($user, 'hrms.employees.view', ['tenant_id' => $tenantId])
            || app(\App\Services\Access\AccessService::class)->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId])
            || app(\App\Domains\HRMS\Services\HrmsScopeService::class)->isCompanyAdmin($user)
        );
        $activeView = $request->input('view', 'overview');

        $widgetQuery = array_filter([
            'preset' => $request->input('preset', 'this_month'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ], fn ($val) => $val !== null && $val !== '');
        $state = $this->layouts->pageState($user, $tenantId, 'hrms', $activeView);

        $viewData = compact(
            'currentEmployee',
            'totalEmployees',
            'probationCount',
            'confirmedCount',
            'noticeCount',
            'newHiresThisMonth',
            'newHiresList',
            'presentCount',
            'lateCount',
            'wfhCount',
            'onLeaveCount',
            'attendancePercent',
            'myTodayAttendance',
            'recentPunches',
            'profileCompletion',
            'myLeaveBalances',
            'myAssignedPlan',
            'myLeaveTypesList',
            'myShiftDetails',
            'myWeeklyPattern',
            'pendingLeaves',
            'pendingWfh',
            'pendingCorrections',
            'pendingExpenses',
            'totalPendingApprovals',
            'upcomingProbationEmployees',
            'activeExits',
            'upcomingHolidays',
            'upcomingBirthdays',
            'upcomingAnniversaries',
            'departments',
            'leaveTypes',
            'recentLateArrivals',
            'unprocessedPenalties',
            'approvedLeaves',
            'latestBroadcasts',
            'totalBroadcastsCount',
            'isHrOrAdmin'
        );

        return view('modules.hrms.dashboard.index', $viewData + $state + [
            'initial' => $this->layouts->preload($state['layout'], $user, $tenantId, $widgetQuery),
            'widgetQuery' => (object) $widgetQuery,
            'activeView' => $activeView,
            'canManage' => $this->layouts->canManage($user),
        ]);
    }

    private function resolveCurrentEmployee(?\App\Models\User $user, int $tenantId): ?Employee
    {
        return app(\App\Domains\HRMS\Services\HrmsScopeService::class)->resolveEmployee($user, $tenantId);
    }

    public function getWebPunchData(?\App\Models\User $user, int $tenantId): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now();
        $currentEmployee = $this->resolveCurrentEmployee($user, $tenantId);

        $myTodayAttendance = null;
        $recentPunches = [];
        $activeShift = null;

        if ($currentEmployee) {
            $myTodayAttendance = Attendance::with('breaks')
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id)
                ->whereDate('date', $today)
                ->first();

            for ($i = 6; $i >= 0; $i--) {
                $pastDate = $now->copy()->subDays($i);
                $pastDateStr = $pastDate->format('Y-m-d');
                $isWeekend = $pastDate->isWeekend();

                $att = Attendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $currentEmployee->id)
                    ->whereDate('date', $pastDateStr)
                    ->first();

                $status = 'unmarked';
                if ($att) {
                    $status = $att->status ?: 'present';
                } elseif ($isWeekend) {
                    $status = 'off';
                } elseif ($pastDate->isPast() && !$pastDate->isToday()) {
                    $status = 'absent';
                }

                $recentPunches[] = [
                    'day_name' => $pastDate->format('D'),
                    'day_num' => $pastDate->format('d'),
                    'date' => $pastDateStr,
                    'status' => $status,
                    'check_in' => $att?->check_in ? Carbon::parse($att->check_in)->format('h:i A') : null,
                    'check_out' => $att?->check_out ? Carbon::parse($att->check_out)->format('h:i A') : null,
                    'is_today' => $pastDate->isToday(),
                ];
            }

            $activeShift = $currentEmployee->resolveShiftForDate($today);
        }

        $startTime = $activeShift?->start_time ? Carbon::parse($activeShift->start_time)->format('h:i A') : '09:00 AM';
        $endTime   = $activeShift?->end_time ? Carbon::parse($activeShift->end_time)->format('h:i A') : '06:00 PM';
        $myShiftDetails = [
            'name' => $activeShift?->name ?: 'Day Shift',
            'badge' => $activeShift?->code ?: 'Default',
            'timing' => $startTime . ' - ' . $endTime,
        ];

        return compact('currentEmployee', 'myTodayAttendance', 'recentPunches', 'myShiftDetails');
    }

    public function getApprovalsData(?\App\Models\User $user, int $tenantId): array
    {
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);

        $pendingLeavesQuery = LeaveRequest::with(['employee.department', 'employee.designation', 'leaveType'])
            ->where('tenant_id', $tenantId)->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingLeavesQuery, $user);
        $pendingLeaves = $pendingLeavesQuery->latest()->take(10)->get();

        $pendingWfhQuery = WfhRequest::with(['employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingWfhQuery, $user);
        $pendingWfh = $pendingWfhQuery->latest()->take(10)->get();

        $pendingCorrectionsQuery = AttendanceCorrection::with(['employee.department', 'attendance'])
            ->where('tenant_id', $tenantId)->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingCorrectionsQuery, $user);
        $pendingCorrections = $pendingCorrectionsQuery->latest()->take(10)->get();

        $pendingExpensesQuery = ExpenseReport::with(['employee.department'])
            ->where('tenant_id', $tenantId)->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingExpensesQuery, $user);
        $pendingExpenses = $pendingExpensesQuery->latest()->take(10)->get();

        $totalPendingApprovals = $pendingLeaves->count() + $pendingWfh->count() + $pendingCorrections->count() + $pendingExpenses->count();

        return compact('pendingLeaves', 'pendingWfh', 'pendingCorrections', 'pendingExpenses', 'totalPendingApprovals');
    }

    public function getLeaveBalancesData(?\App\Models\User $user, int $tenantId): array
    {
        $currentEmployee = $this->resolveCurrentEmployee($user, $tenantId);

        $myAssignedPlan = null;
        if ($currentEmployee && $currentEmployee->leave_plan_id) {
            $myAssignedPlan = LeavePlan::with('types')->find($currentEmployee->leave_plan_id);
        }
        if (!$myAssignedPlan && $currentEmployee) {
            $myAssignedPlan = LeavePlan::where('tenant_id', $tenantId)->where('status', true)->with('types')->first();
        }

        $planTypesCollection = collect();
        if ($myAssignedPlan && $myAssignedPlan->types->isNotEmpty()) {
            $planTypesCollection = $myAssignedPlan->types;
        } else {
            $dbBalances = $currentEmployee ? LeaveBalance::where('tenant_id', $tenantId)->where('employee_id', $currentEmployee->id)->with('leaveType')->get() : collect();
            if ($dbBalances->isNotEmpty()) {
                $planTypesCollection = $dbBalances->pluck('leaveType')->filter()->unique('id');
            } elseif ($currentEmployee) {
                $planTypesCollection = LeaveType::where('tenant_id', $tenantId)->where('status', true)->take(4)->get();
            }
        }

        $myLeaveTypesList = [];
        foreach ($planTypesCollection as $type) {
            $bal = $currentEmployee ? LeaveBalance::where('tenant_id', $tenantId)->where('employee_id', $currentEmployee->id)->where('leave_type_id', $type->id)->first() : null;
            $allocated = $bal ? floatval($bal->allocated) : floatval($type->quota ?? 12);
            $used = $bal ? floatval($bal->used) : 0;
            $remaining = $bal ? floatval($bal->remaining) : $allocated;

            $myLeaveTypesList[] = [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code ?: strtoupper(substr($type->name, 0, 2)),
                'color' => $type->color ?: '#3b82f6',
                'allocated' => $allocated,
                'used' => $used,
                'remaining' => $remaining,
                'type' => $type->type ?? 'paid',
                'description' => $type->description ?? '',
                'rules' => is_array($type->rules) ? $type->rules : (json_decode($type->rules ?? '[]', true) ?: []),
            ];
        }

        return compact('myLeaveTypesList', 'myAssignedPlan');
    }

    public function getShiftData(?\App\Models\User $user, int $tenantId): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now();
        $currentEmployee = $this->resolveCurrentEmployee($user, $tenantId);

        $activeShift = $currentEmployee ? $currentEmployee->resolveShiftForDate($today) : null;
        $myShiftDetails = [
            'name' => $activeShift?->name ?: 'General Shift',
            'badge' => $activeShift?->code ?: 'Default',
            'timing' => ($activeShift?->start_time ? Carbon::parse($activeShift->start_time)->format('h:i A') : '09:00 AM') . ' - ' . ($activeShift?->end_time ? Carbon::parse($activeShift->end_time)->format('h:i A') : '06:00 PM'),
            'overtime_status' => ($activeShift?->overtime_allowed ?? false) ? 'Allowed' : 'Not Allowed',
            'is_ot_allowed' => (bool) ($activeShift?->overtime_allowed ?? false),
        ];

        $myWeeklyPattern = [];
        $startOfWeek = $now->copy()->startOfWeek(Carbon::SUNDAY);
        for ($i = 0; $i < 7; $i++) {
            $dayDate = $startOfWeek->copy()->addDays($i);
            $dayDateStr = $dayDate->format('Y-m-d');
            $dayShift = $currentEmployee ? $currentEmployee->resolveShiftForDate($dayDateStr) : null;

            if ($dayShift) {
                $myWeeklyPattern[] = [
                    'day'    => $dayDate->format('D'),
                    'status' => $dayShift->name,
                    'is_off' => false,
                ];
            } else {
                $isWeekend = ($dayDate->dayOfWeek === 0);
                $myWeeklyPattern[] = [
                    'day'    => $dayDate->format('D'),
                    'status' => $isWeekend ? 'Day Off' : ($activeShift ? $activeShift->name : 'Day Shift'),
                    'is_off' => $isWeekend,
                ];
            }
        }

        return compact('myShiftDetails', 'myWeeklyPattern');
    }

    public function getBroadcastsData(?\App\Models\User $user, int $tenantId): array
    {
        $currentEmployee = $this->resolveCurrentEmployee($user, $tenantId);

        $latestBroadcasts = Broadcast::with(['receipts', 'comments.employee'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->latest()
            ->take(5)
            ->get();

        $totalBroadcastsCount = Broadcast::where('tenant_id', $tenantId)->where('status', 'published')->count();

        return compact('latestBroadcasts', 'totalBroadcastsCount', 'currentEmployee');
    }

    public function getProbationData(?\App\Models\User $user, int $tenantId): array
    {
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
        $query = Employee::with(['department'])
            ->where('tenant_id', $tenantId)
            ->where('employee_stage', 'Probation')
            ->whereNotNull('probation_end_date');
        $scopeService->applyEmployeeScope($query, $user);

        $upcomingProbationEmployees = $query
            ->orderBy('probation_end_date', 'asc')
            ->take(10)
            ->get();

        return compact('upcomingProbationEmployees');
    }

    public function getExitData(?\App\Models\User $user, int $tenantId): array
    {
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
        $query = EmployeeExit::with(['employee.department'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['initiated', 'in_clearance', 'approved']);
        $scopeService->applyRelatedScope($query, $user);

        $activeExits = $query
            ->latest()
            ->take(10)
            ->get();

        return compact('activeExits');
    }

    public function getKpiData(?\App\Models\User $user, int $tenantId): array
    {
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
        $today = Carbon::today()->format('Y-m-d');
        
        $empQuery = Employee::where('tenant_id', $tenantId);
        $scopeService->applyEmployeeScope($empQuery, $user);
        $totalEmployees = (clone $empQuery)->count();
        $probationCount = (clone $empQuery)->where('employee_stage', 'Probation')->count();
        $confirmedCount = (clone $empQuery)->where('employee_stage', 'Confirmed')->count();
        $noticeCount = (clone $empQuery)->whereIn('employee_stage', ['Notice Period', 'Serving Notice'])->count();

        $attQuery = Attendance::where('tenant_id', $tenantId)->whereDate('date', $today);
        $scopeService->applyRelatedScope($attQuery, $user);
        $todayAttendances = $attQuery->get();
        $presentCount = $todayAttendances->whereIn('status', ['present', 'late', 'half_day'])->count();
        $lateCount = $todayAttendances->where('status', 'late')->count();

        $wfhQuery = WfhRequest::where('tenant_id', $tenantId)->where('status', 'approved')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today);
        $scopeService->applyRelatedScope($wfhQuery, $user);
        $wfhCount = $wfhQuery->count() + $todayAttendances->where('location_type', 'wfh')->count();

        $leaveQuery = LeaveRequest::where('tenant_id', $tenantId)->where('status', 'approved')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today);
        $scopeService->applyRelatedScope($leaveQuery, $user);
        $onLeaveCount = $leaveQuery->count();

        $attendancePercent = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100, 1) : 0;

        $pendingLeavesQuery = LeaveRequest::where('tenant_id', $tenantId)->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingLeavesQuery, $user);
        $pendingLeaves = $pendingLeavesQuery->get();

        $pendingWfhQuery = WfhRequest::where('tenant_id', $tenantId)->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingWfhQuery, $user);
        $pendingWfh = $pendingWfhQuery->get();

        $pendingCorrectionsQuery = AttendanceCorrection::where('tenant_id', $tenantId)->where('status', 'pending');
        $scopeService->applyRelatedScope($pendingCorrectionsQuery, $user);
        $pendingCorrections = $pendingCorrectionsQuery->get();

        $totalPendingApprovals = $pendingLeaves->count() + $pendingWfh->count() + $pendingCorrections->count();

        $probationQuery = Employee::where('tenant_id', $tenantId)->where('employee_stage', 'Probation')->whereNotNull('probation_end_date');
        $scopeService->applyEmployeeScope($probationQuery, $user);
        $upcomingProbationEmployees = $probationQuery->get();

        $exitQuery = EmployeeExit::where('tenant_id', $tenantId)->whereIn('status', ['initiated', 'in_clearance', 'approved']);
        $scopeService->applyRelatedScope($exitQuery, $user);
        $activeExits = $exitQuery->get();

        return compact('totalEmployees', 'probationCount', 'confirmedCount', 'noticeCount', 'presentCount', 'lateCount', 'wfhCount', 'onLeaveCount', 'attendancePercent', 'pendingLeaves', 'pendingWfh', 'pendingCorrections', 'totalPendingApprovals', 'upcomingProbationEmployees', 'activeExits');
    }

    public function getLateArrivalsData(?\App\Models\User $user, int $tenantId): array
    {
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
        $now = Carbon::now();
        $query = Attendance::with(['employee.department'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['late', 'half_day'])
            ->whereDate('date', '>=', $now->copy()->subDays(7));
        $scopeService->applyRelatedScope($query, $user);

        $recentLateArrivals = $query
            ->orderBy('date', 'desc')
            ->take(10)
            ->get();

        return compact('recentLateArrivals');
    }

    public function getPenaltiesData(?\App\Models\User $user, int $tenantId): array
    {
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
        $query = EmployeePenalty::with(['employee.department'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'pending')->orWhere('status', 'unprocessed');
            });
        $scopeService->applyRelatedScope($query, $user);

        $unprocessedPenalties = $query
            ->latest()
            ->take(10)
            ->get();

        return compact('unprocessedPenalties');
    }

    public function getApprovedLeavesData(?\App\Models\User $user, int $tenantId): array
    {
        $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
        $today = Carbon::today()->format('Y-m-d');
        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $today);
        $scopeService->applyRelatedScope($query, $user);

        $approvedLeaves = $query
            ->orderBy('start_date', 'asc')
            ->take(10)
            ->get();

        return compact('approvedLeaves');
    }

    public function getHolidaysData(?\App\Models\User $user, int $tenantId): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $upcomingHolidays = HolidayCalendar::where('tenant_id', $tenantId)
            ->where('status', true)
            ->whereDate('holiday_date', '>=', $today)
            ->orderBy('holiday_date', 'asc')
            ->take(10)
            ->get();

        return compact('upcomingHolidays');
    }

    public function getCelebrationsData(?\App\Models\User $user, int $tenantId): array
    {
        $now = Carbon::now();
        $allActiveEmployees = Employee::where('tenant_id', $tenantId)->where('status', true)->get();

        $upcomingBirthdays = $allActiveEmployees->filter(function ($emp) use ($now) {
            return $emp->date_of_birth && Carbon::parse($emp->date_of_birth)->month === $now->month;
        })->sortBy(fn ($emp) => Carbon::parse($emp->date_of_birth)->day)->values();

        $upcomingAnniversaries = $allActiveEmployees->filter(function ($emp) use ($now) {
            return $emp->date_of_joining && Carbon::parse($emp->date_of_joining)->month === $now->month && Carbon::parse($emp->date_of_joining)->year < $now->year;
        })->sortBy(fn ($emp) => Carbon::parse($emp->date_of_joining)->day)->values();

        return compact('upcomingBirthdays', 'upcomingAnniversaries');
    }

    public function getDepartmentData(?\App\Models\User $user, int $tenantId): array
    {
        $totalEmployees = Employee::where('tenant_id', $tenantId)->count();
        $departments = Department::where('tenant_id', $tenantId)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->take(6)
            ->get();

        return compact('departments', 'totalEmployees');
    }

    public function getNewJoineesData(?\App\Models\User $user, int $tenantId): array
    {
        $now = Carbon::now();
        $today = Carbon::today()->format('Y-m-d');
        $newHiresList = Employee::with(['department', 'designation'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('date_of_joining')
            ->whereDate('date_of_joining', '>=', $now->copy()->subDays(30))
            ->whereDate('date_of_joining', '<=', $today)
            ->orderBy('date_of_joining', 'desc')
            ->take(6)
            ->get();

        $newHiresThisMonth = $newHiresList->count();

        return compact('newHiresList', 'newHiresThisMonth');
    }

    /**
     * Interactive Web Punch Handler for Employees (Clock-In, Clock-Out, Breaks).
     */
    public function webPunch(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $action = $request->input('action', 'in'); // in, out, break_out, break_in
        $locationType = $request->input('location_type', 'office');
        $now = Carbon::now();
        $today = $now->format('Y-m-d');

        // Resolve current employee strictly for authenticated user
        $employee = $this->resolveCurrentEmployee(auth()->user(), $tenantId);

        if (!$employee) {
            return redirect()->back()->with('error', 'No active employee profile linked to your account to record attendance.');
        }

        // Fetch today's Attendance record
        $attendance = Attendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        // 1. Clock In (Check-In)
        if ($action === 'in') {
            if ($attendance && $attendance->check_in) {
                return redirect()->back()->with('error', 'You have already clocked in today at ' . Carbon::parse($attendance->check_in)->format('h:i A') . '.');
            }

            if (!$attendance) {
                $attendance = Attendance::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'date' => $today,
                    'check_in' => $now,
                    'location_type' => $locationType,
                    'status' => $now->format('H:i') > '09:45' ? 'late' : 'present',
                ]);
            } else {
                $attendance->update([
                    'check_in' => $now,
                    'location_type' => $locationType,
                    'status' => $now->format('H:i') > '09:45' ? 'late' : 'present',
                ]);
            }

            BiometricPunchLog::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'punch_time' => $now,
                'punch_type' => 'in',
            ]);

            return redirect()->back()->with('success', 'Web Clock-In successful! Logged in at ' . $now->format('h:i A') . ' (' . ucfirst($locationType) . ').');
        }

        if (!$attendance || !$attendance->check_in) {
            return redirect()->back()->with('error', 'Please clock-in first before performing this action.');
        }

        // 2. Start Break (Take Break)
        if ($action === 'break_out') {
            if (!$attendance->check_in) {
                return redirect()->back()->with('error', 'Please clock-in first before taking a break.');
            }

            // Create open break record (break_in records the start of break timestamp)
            AttendanceBreak::create([
                'attendance_id' => $attendance->id,
                'break_in' => $now,
            ]);

            BiometricPunchLog::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'punch_time' => $now,
                'punch_type' => 'break_out',
            ]);

            return redirect()->back()->with('success', 'Break started at ' . $now->format('h:i A') . '. Enjoy your break!');
        }

        // 3. End Break (Resume Work)
        if ($action === 'break_in') {
            $activeBreak = AttendanceBreak::where('attendance_id', $attendance->id)
                ->whereNull('break_out')
                ->latest()
                ->first();

            if ($activeBreak) {
                $duration = max(0, intval(Carbon::parse($activeBreak->break_in)->diffInMinutes($now)));
                $activeBreak->update([
                    'break_out' => $now,
                    'duration_minutes' => $duration,
                ]);

                $totalBreakMinutes = AttendanceBreak::where('attendance_id', $attendance->id)->sum('duration_minutes') ?? 0;
                $attendance->update([
                    'total_break_hours' => round($totalBreakMinutes / 60, 2),
                ]);
            }

            BiometricPunchLog::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'punch_time' => $now,
                'punch_type' => 'break_in',
            ]);

            return redirect()->back()->with('success', 'Break concluded at ' . $now->format('h:i A') . '. Resumed work!');
        }

        // 4. Clock Out (Check-Out)
        if ($action === 'out') {
            if (!$attendance->check_in) {
                return redirect()->back()->with('error', 'Cannot clock-out without a prior clock-in record today.');
            }

            // Auto-close any pending break
            $openBreak = AttendanceBreak::where('attendance_id', $attendance->id)->whereNull('break_out')->latest()->first();
            if ($openBreak) {
                $duration = max(0, intval(Carbon::parse($openBreak->break_in)->diffInMinutes($now)));
                $openBreak->update([
                    'break_out' => $now,
                    'duration_minutes' => $duration,
                ]);
            }

            // Calculate total work hours
            $checkInTime = Carbon::parse($attendance->check_in);
            $grossMinutes = max(0, intval($now->diffInMinutes($checkInTime)));
            $totalBreakMinutes = AttendanceBreak::where('attendance_id', $attendance->id)->sum('duration_minutes') ?? 0;
            $netMinutes = max(0, $grossMinutes - $totalBreakMinutes);
            $totalWorkHours = round($netMinutes / 60, 2);

            $attendance->update([
                'check_out' => $now,
                'total_work_hours' => $totalWorkHours,
                'total_break_hours' => round($totalBreakMinutes / 60, 2),
            ]);

            BiometricPunchLog::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'punch_time' => $now,
                'punch_type' => 'out',
            ]);

            return redirect()->back()->with('success', 'Web Clock-Out successful! Shift finalized at ' . $now->format('h:i A') . ' (Total Work: ' . $totalWorkHours . ' hrs).');
        }

        return redirect()->back();
    }
}
