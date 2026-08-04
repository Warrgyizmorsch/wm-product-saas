<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Repositories\AttendanceRepositoryInterface;
use App\Domains\HRMS\Models\Department;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['date', 'department_id', 'search']);
        
        $date = $filters['date'] ?? \Carbon\Carbon::today()->format('Y-m-d');
        $departmentId = $filters['department_id'] ?? null;
        $search = $filters['search'] ?? null;
        $sort = $request->input('sort', 'name_asc');

        $query = \App\Domains\HRMS\Models\Employee::where('status', true)
            ->with(['department', 'designation', 'attendances' => function($q) use ($date) {
                $q->where('date', $date)->with('breaks');
            }]);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        // Sorting logic
        if ($sort === 'name_desc') {
            $query->orderBy('full_name', 'desc');
        } elseif ($sort === 'checkin_asc' || $sort === 'checkin_desc') {
            $order = ($sort === 'checkin_desc') ? 'desc' : 'asc';
            $query->select('employees.*')
                ->leftJoin('attendances', function($join) use ($date) {
                    $join->on('employees.id', '=', 'attendances.employee_id')
                         ->where('attendances.date', '=', $date);
                })
                ->orderByRaw("CASE WHEN attendances.check_in IS NULL THEN 1 ELSE 0 END, attendances.check_in {$order}");
        } else {
            $query->orderBy('full_name', 'asc');
        }

        $employees = $query->paginate(15)->withQueryString();
        $departments = Department::where('status', true)->orderBy('name')->get();

        // Calculate simple stats for today
        $today = \Carbon\Carbon::today()->format('Y-m-d');
        $stats = [
            'total_present' => Attendance::where('date', $today)->count(),
            'total_on_break' => Attendance::where('date', $today)
                ->whereHas('breaks', function($q) {
                    $q->whereNull('break_out');
                })->count(),
            'total_late' => Attendance::where('date', $today)->where('status', 'late')->count(),
            'total_wfh' => Attendance::where('date', $today)->where('location_type', 'wfh')->count(),
        ];

        return view('modules.hrms.attendance.index', compact('employees', 'departments', 'filters', 'stats', 'sort'));
    }

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $this->attendanceRepository->checkIn($validated['employee_id']);

        return redirect()->back()->with('success', 'Clocked in successfully!');
    }

    public function checkOut(Request $request, Attendance $attendance)
    {
        $this->attendanceRepository->checkOut($attendance->id);

        return redirect()->back()->with('success', 'Clocked out successfully!');
    }

    public function breakIn(Request $request, Attendance $attendance)
    {
        $this->attendanceRepository->breakIn($attendance->id);

        return redirect()->back()->with('success', 'Break started successfully!');
    }

    public function breakOut(Request $request, Attendance $attendance)
    {
        $this->attendanceRepository->breakOut($attendance->id);

        return redirect()->back()->with('success', 'Break ended successfully!');
    }

    public function getEmployeeLogs(\App\Domains\HRMS\Models\Employee $employee)
    {
        $logs = Attendance::where('employee_id', $employee->id)
            ->with('breaks')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'employee' => [
                'name' => $employee->display_name,
                'code' => $employee->employee_id,
            ],
            'logs' => $logs->map(function ($log) {
                $breaksHtml = '';
                if ($log->breaks->isNotEmpty()) {
                    foreach ($log->breaks as $index => $brk) {
                        $brkIn = \Carbon\Carbon::parse($brk->break_in)->format('h:i A');
                        $brkOut = $brk->break_out ? \Carbon\Carbon::parse($brk->break_out)->format('h:i A') : 'Active';
                        $brkDur = $brk->duration_minutes !== null ? $brk->duration_minutes . 'm' : 'Active';
                        $breaksHtml .= "<div class='fs-10 text-muted' style='line-height: 1.3;'>{$brkIn} - {$brkOut} ({$brkDur})</div>";
                    }
                    if ($log->total_break_hours > 0) {
                        $breaksHtml .= "<div class='fw-bold mt-1 text-dark' style='font-size: 10px;'>Total: {$log->formatted_break_hours}</div>";
                    }
                } else {
                    $breaksHtml = '-';
                }

                $statusBadge = '';
                $status = $log->status ?: 'present';
                if ($status === 'present') {
                    $statusBadge = '<span class="badge bg-soft-success text-success">Present</span>';
                } elseif ($status === 'late') {
                    $statusBadge = '<span class="badge bg-soft-warning text-warning">Late</span>';
                } elseif ($status === 'half_day') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Half Day</span>';
                } elseif ($status === 'under_hours') {
                    $statusBadge = '<span class="badge bg-soft-secondary text-slate">Under Hours</span>';
                } else {
                    $statusBadge = '<span class="badge bg-soft-primary text-primary">' . ucfirst($status) . '</span>';
                }

                return [
                    'date' => $log->date->format('M d, Y'),
                    'location_type' => $log->formatted_location_type,
                    'check_in' => $log->check_in ? \Carbon\Carbon::parse($log->check_in)->format('h:i A') : '-',
                    'check_out' => $log->check_out ? \Carbon\Carbon::parse($log->check_out)->format('h:i A') : ($log->check_in ? 'Active' : '-'),
                    'breaks' => $breaksHtml,
                    'work_hours' => $log->check_out ? $log->formatted_work_hours : ($log->check_in ? 'In progress' : '-'),
                    'status' => $statusBadge,
                ];
            }),
        ]);
    }
}
