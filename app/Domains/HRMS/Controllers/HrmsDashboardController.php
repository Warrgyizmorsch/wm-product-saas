<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\HolidayCalendar;
use App\Domains\HRMS\Models\LeaveBalance;
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
    /**
     * Display the comprehensive Role-Ready HRMS Dashboard.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now();

        // 1. Resolve current logged-in employee context
        $currentEmployee = null;
        if (auth()->check()) {
            $user = auth()->user();
            $currentEmployee = Employee::where('tenant_id', $tenantId)
                ->where(function ($q) use ($user) {
                    if ($user->email) {
                        $q->where('office_email', $user->email)
                          ->orWhere('personal_email', $user->email);
                    }
                })
                ->first();
        }
        if (!$currentEmployee) {
            $currentEmployee = Employee::where('tenant_id', $tenantId)->first();
        }

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

        // 5. Unified Action Center / Pending Inboxes (Leaves, WFH, Regularizations, Expenses)
        $pendingLeaves = LeaveRequest::with(['employee.department', 'employee.designation', 'leaveType'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->latest()
            ->take(20)
            ->get();

        $pendingWfh = WfhRequest::with(['employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->latest()
            ->take(20)
            ->get();

        $pendingCorrections = AttendanceCorrection::with(['employee.department', 'attendance'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->latest()
            ->take(20)
            ->get();

        $pendingExpenses = ExpenseReport::with(['employee.department'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->latest()
            ->take(20)
            ->get();

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

        // 9. Celebrations (Birthdays & Work Anniversaries from real active employees)
        $allActiveEmployees = Employee::with(['department', 'designation'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'Active')
            ->get();

        $upcomingBirthdays = $allActiveEmployees->filter(function ($emp) use ($now) {
            if (!$emp->date_of_birth) return false;
            $bday = Carbon::parse($emp->date_of_birth);
            return $bday->month === $now->month && $bday->day >= $now->day;
        })->take(5);

        $upcomingAnniversaries = $allActiveEmployees->filter(function ($emp) use ($now) {
            if (!$emp->date_of_joining) return false;
            $doj = Carbon::parse($emp->date_of_joining);
            return $doj->month === $now->month && $doj->year < $now->year;
        })->take(5);

        // 10. Department Distribution Breakdown
        $departments = Department::where('tenant_id', $tenantId)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->take(6)
            ->get();

        // 11. Late Arrivals (Last 7 Days) & Unprocessed Penalties
        $recentLateArrivals = Attendance::with(['employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['late', 'half_day'])
            ->whereDate('date', '>=', $now->copy()->subDays(7))
            ->orderBy('date', 'desc')
            ->take(15)
            ->get();

        $unprocessedPenalties = EmployeePenalty::with(['employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', 'pending')
                  ->orWhere('status', 'unprocessed');
            })
            ->orderBy('date', 'desc')
            ->take(15)
            ->get();

        // 12. Leave Types & Approved Leaves
        $leaveTypes = LeaveType::where('tenant_id', $tenantId)->where('status', true)->get();
        if ($leaveTypes->isEmpty()) {
            $leaveTypes = LeaveType::where('tenant_id', $tenantId)->get();
        }

        $approvedLeaves = LeaveRequest::with(['employee.department', 'employee.designation', 'leaveType'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date', 'asc')
            ->take(15)
            ->get();

        return view('modules.hrms.dashboard.index', compact(
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
            'approvedLeaves'
        ));
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

        // Resolve employee
        $employeeId = $request->input('employee_id');
        $employee = null;
        if ($employeeId) {
            $employee = Employee::where('tenant_id', $tenantId)->find($employeeId);
        }
        if (!$employee && auth()->check()) {
            $user = auth()->user();
            $employee = Employee::where('tenant_id', $tenantId)
                ->where(function ($q) use ($user) {
                    if ($user->email) {
                        $q->where('office_email', $user->email)
                          ->orWhere('personal_email', $user->email);
                    }
                })
                ->first();
        }
        if (!$employee) {
            $employee = Employee::where('tenant_id', $tenantId)->first();
        }

        if (!$employee) {
            return redirect()->back()->with('error', 'No active employee profile found to record attendance.');
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
