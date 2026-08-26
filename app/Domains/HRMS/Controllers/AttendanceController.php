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
        $filters = $request->only(['date', 'department_id', 'search', 'status']);
        
        $date = $filters['date'] ?? \Carbon\Carbon::today()->format('Y-m-d');
        $departmentId = $filters['department_id'] ?? null;
        $search = $filters['search'] ?? null;
        $statusFilter = $filters['status'] ?? null;
        $sort = $request->input('sort', 'name_asc');
        $view = $request->input('view', 'date');

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
        $departments = Department::where('status', true)->orderBy('name')->get();

        if ($view === 'corrections') {
            $query = \App\Domains\HRMS\Models\AttendanceCorrection::with(['employee', 'attendance']);

            $correctionsStatus = $request->get('corrections_status');
            if (!empty($correctionsStatus)) {
                $query->where('status', $correctionsStatus);
            }

            $payrollMonth = $request->get('payroll_month');
            if (!empty($payrollMonth)) {
                $carbonMonth = \Carbon\Carbon::parse($payrollMonth . '-01');
                $query->whereBetween('date', [$carbonMonth->copy()->startOfMonth(), $carbonMonth->copy()->endOfMonth()]);
            }

            $corrections = $query->orderBy('created_at', 'desc')
                ->paginate(10)
                ->withQueryString();

            $employees = collect();
            $dates = collect();
            $statsByDate = collect();

            return view('modules.hrms.attendance.index', compact(
                'corrections',
                'employees',
                'dates',
                'statsByDate',
                'view',
                'departments',
                'filters',
                'stats',
                'sort'
            ));
        }

        if ($view === 'date') {
            $dateQuery = Attendance::select('date')
                ->groupBy('date')
                ->orderBy('date', 'desc');

            if ($search) {
                $dateQuery->whereIn('date', function($q) use ($search) {
                    $q->select('date')
                      ->from('attendances')
                      ->whereIn('employee_id', function($sq) use ($search) {
                          $sq->select('id')
                            ->from('employees')
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%");
                      });
                });
            }

            if ($departmentId) {
                $dateQuery->whereIn('date', function($q) use ($departmentId) {
                    $q->select('date')
                      ->from('attendances')
                      ->whereIn('employee_id', function($sq) use ($departmentId) {
                          $sq->select('id')
                            ->from('employees')
                            ->where('department_id', $departmentId);
                      });
                });
            }

            if ($statusFilter) {
                $dateQuery->whereIn('date', function($q) use ($statusFilter) {
                    $q->select('date')
                      ->from('attendances')
                      ->where('status', $statusFilter);
                });
            }

            if (isset($filters['date']) && $filters['date']) {
                $dateQuery->where('date', $filters['date']);
            }

            $dates = $dateQuery->paginate(10)->withQueryString();
            
            $statsByDate = collect();
            if ($dates->isNotEmpty()) {
                $statsByDate = Attendance::whereIn('date', $dates->pluck('date'))
                    ->selectRaw('date, status, location_type, COUNT(*) as count')
                    ->groupBy('date', 'status', 'location_type')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->date->format('Y-m-d');
                    });
            }

            $employees = collect();

            return view('modules.hrms.attendance.index', compact(
                'employees',
                'dates',
                'statsByDate',
                'view',
                'departments',
                'filters',
                'stats',
                'sort'
            ));
        }

        // view === 'employee'
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

        if ($statusFilter) {
            $query->whereHas('attendances', function($q) use ($date, $statusFilter) {
                $q->where('date', $date)->where('status', $statusFilter);
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

        $employees = $query->paginate(10)->withQueryString();
        $dates = collect();
        $statsByDate = collect();

        return view('modules.hrms.attendance.index', compact(
            'employees',
            'dates',
            'statsByDate',
            'view',
            'departments',
            'filters',
            'stats',
            'sort'
        ));
    }

    public function myAttendance(Request $request)
    {
        $user = auth()->user();

        $employee = \App\Domains\HRMS\Models\Employee::where('user_id', $user->id)
            ->with(['department', 'designation'])
            ->firstOrFail();

        $date = $request->input('date');
        $status = $request->input('status');
        $sort = $request->input('sort', 'date_desc');
        $search = $request->input('search');

        $monthFilter = $request->input('month');
        if (!$request->has('month') && !$request->has('date')) {
            $monthFilter = now()->format('Y-m');
        }

        $query = Attendance::where('employee_id', $employee->id)
            ->with('breaks');

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('location_type', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%");

                // Parse month prefixes, names, and year/day integers for advanced date searching
                $cleanSearch = str_replace([',', '.'], '', strtolower(trim($search)));
                $tokens = array_filter(explode(' ', $cleanSearch));
                
                $monthsNames = [
                    1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april',
                    5 => 'may', 6 => 'june', 7 => 'july', 8 => 'august',
                    9 => 'september', 10 => 'october', 11 => 'november', 12 => 'december'
                ];

                $matchedMonths = [];
                $yearNum = null;
                $dayNum = null;

                foreach ($tokens as $token) {
                    if (is_numeric($token)) {
                        $val = (int)$token;
                        if ($val > 1000 && $val < 3000) {
                            $yearNum = $val;
                        } elseif ($val >= 1 && $val <= 31) {
                            $dayNum = $val;
                        }
                    } else {
                        // Check if token matches prefix of any month (either full month name or 3-letter abbreviation)
                        foreach ($monthsNames as $num => $name) {
                            if (str_starts_with($name, $token) || str_starts_with(substr($name, 0, 3), $token)) {
                                $matchedMonths[] = $num;
                            }
                        }
                    }
                }

                $matchedMonths = array_unique($matchedMonths);

                if (!empty($matchedMonths)) {
                    $q->orWhere(function($sub) use ($matchedMonths, $yearNum, $dayNum) {
                        $sub->where(function($monthSub) use ($matchedMonths) {
                            foreach ($matchedMonths as $mNum) {
                                $monthSub->orWhereMonth('date', $mNum);
                            }
                        });
                        if ($yearNum) {
                            $sub->whereYear('date', $yearNum);
                        }
                        if ($dayNum) {
                            $sub->whereDay('date', $dayNum);
                        }
                    });
                }
            });
        }

        // Date filter
        if ($date) {
            $query->whereDate('date', $date);
        }

        // Month filter
        if ($monthFilter) {
            $parts = explode('-', $monthFilter);
            if (count($parts) === 2) {
                $query->whereYear('date', $parts[0])
                      ->whereMonth('date', $parts[1]);
            }
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Sorting
        switch ($sort) {
            case 'date_asc':
                $query->orderBy('date', 'asc');
                break;

            case 'checkin_asc':
                $query->orderBy('check_in', 'asc');
                break;

            case 'checkin_desc':
                $query->orderBy('check_in', 'desc');
                break;

            default:
                $query->orderBy('date', 'desc');
                break;
        }

        $attendances = $query->get();

        $correctionRequests = \App\Domains\HRMS\Models\AttendanceCorrection::where('employee_id', $employee->id)
            ->get()
            ->groupBy(function($c) {
                return $c->date instanceof \Carbon\Carbon ? $c->date->format('Y-m-d') : \Carbon\Carbon::parse($c->date)->format('Y-m-d');
            });

        return view(
            'modules.hrms.attendance.myAttendance',
            compact(
                'employee',
                'attendances',
                'date',
                'status',
                'sort',
                'search',
                'monthFilter',
                'correctionRequests'
            )
        );
    }

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'selfie'      => 'nullable|string',
        ]);

        try {
            $this->attendanceRepository->checkIn(
                $validated['employee_id'],
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $request->input('selfie')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $activeTab = 'attendance';
            $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $validated['employee_id'])
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', \Carbon\Carbon::today()->format('Y-m-d'))
                ->whereDate('end_date', '>=', \Carbon\Carbon::today()->format('Y-m-d'))
                ->exists();

            if ($hasWfh) {
                $activeTab = 'wfh';
            }

            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('active_tab', $activeTab);
        }

        return redirect()->back()->with('success', 'Clocked in successfully!');
    }

    public function checkOut(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'selfie'      => 'nullable|string',
        ]);

        try {
            $this->attendanceRepository->checkOut(
                $attendance->id,
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $request->input('selfie')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $activeTab = 'attendance';
            $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $attendance->employee_id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', \Carbon\Carbon::today()->format('Y-m-d'))
                ->whereDate('end_date', '>=', \Carbon\Carbon::today()->format('Y-m-d'))
                ->exists();

            if ($hasWfh) {
                $activeTab = 'wfh';
            }

            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('active_tab', $activeTab);
        }

        return redirect()->back()->with('success', 'Clocked out successfully!');
    }

    public function breakIn(Request $request, Attendance $attendance)
    {
        $this->attendanceRepository->breakIn($attendance->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['status' => 'success', 'message' => 'Break started successfully.']);
        }

        return redirect()->back()->with('success', 'Break started successfully!');
    }

    public function breakOut(Request $request, Attendance $attendance)
    {
        $this->attendanceRepository->breakOut($attendance->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['status' => 'success', 'message' => 'Break ended successfully.']);
        }

        return redirect()->back()->with('success', 'Break ended successfully!');
    }

    public function getEmployeeLogs(\App\Domains\HRMS\Models\Employee $employee)
    {
        $logs = Attendance::where('employee_id', $employee->id)
            ->with(['breaks', 'locationLogs'])
            ->orderBy('date', 'desc')
            ->get();

        $approvedOvertimeDates = \App\Domains\HRMS\Models\OvertimeRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->pluck('date')
            ->map(function ($d) {
                return $d->format('Y-m-d');
            })
            ->toArray();

        return response()->json([
            'employee' => [
                'name' => $employee->display_name,
                'code' => $employee->employee_id,
            ],
            'logs' => $logs->map(function ($log) use ($approvedOvertimeDates) {
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

                $status = $log->status ?: 'present';
                $isAbsentOrLeave = in_array($status, ['absent', 'on_leave']);

                if ($status === 'present') {
                    $statusBadge = '<span class="badge bg-soft-success text-success">Present</span>';
                } elseif ($status === 'wfh') {
                    $statusBadge = '<span class="badge bg-soft-info text-info">WFH</span>';
                } elseif ($status === 'late') {
                    $statusBadge = '<span class="badge bg-soft-warning text-warning">Late</span>';
                } elseif ($status === 'half_day') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Half Day</span>';
                } elseif ($status === 'on_leave') {
                    $statusBadge = '<span class="badge bg-soft-primary text-primary">On Leave</span>';
                } elseif ($status === 'absent') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Absent</span>';
                } elseif ($status === 'under_hours') {
                    $statusBadge = '<span class="badge bg-soft-secondary text-secondary">Under Hours</span>';
                } else {
                    $statusBadge = '<span class="badge bg-soft-dark text-dark">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
                }

                return [
                    'id' => $log->id,
                    'date' => $log->date->format('M d, Y'),
                    'location_type' => $log->formatted_location_type,
                    'check_in' => ($log->check_in && !$isAbsentOrLeave) ? \Carbon\Carbon::parse($log->check_in)->format('h:i A') : '-',
                    'check_out' => ($log->check_out && !$isAbsentOrLeave) ? \Carbon\Carbon::parse($log->check_out)->format('h:i A') : '-',
                    'breaks' => $breaksHtml,
                    'work_hours' => ($log->check_out && !$isAbsentOrLeave) ? $log->formatted_work_hours : '-',
                    'status' => $statusBadge,
                    'status_raw' => $status,
                    'has_overtime' => in_array($log->date->format('Y-m-d'), $approvedOvertimeDates),
                    'check_in_latitude' => $log->check_in_latitude,
                    'check_in_longitude' => $log->check_in_longitude,
                    'check_out_latitude' => $log->check_out_latitude,
                    'check_out_longitude' => $log->check_out_longitude,
                    'check_in_selfie_url' => $log->check_in_selfie_path ? asset('storage/' . $log->check_in_selfie_path) : null,
                    'check_out_selfie_url' => $log->check_out_selfie_path ? asset('storage/' . $log->check_out_selfie_path) : null,
                    'location_logs' => $log->locationLogs->map(fn($l) => [
                        'lat' => (float)$l->latitude,
                        'lng' => (float)$l->longitude,
                        'time' => $l->created_at ? $l->created_at->format('h:i A') : ''
                    ])->toArray(),
                ];
            }),
        ]);
    }

    public function getDateLogs($date)
    {
        $logs = Attendance::where('date', $date)
            ->with(['employee.department', 'breaks', 'locationLogs'])
            ->get();

        $approvedOvertimeEmployeeIds = \App\Domains\HRMS\Models\OvertimeRequest::where('date', $date)
            ->where('status', 'approved')
            ->pluck('employee_id')
            ->toArray();

        return response()->json([
            'date' => \Carbon\Carbon::parse($date)->format('M d, Y'),
            'logs' => $logs->map(function ($log) use ($approvedOvertimeEmployeeIds) {
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

                $status = $log->status ?: 'present';
                $isAbsentOrLeave = in_array($status, ['absent', 'on_leave']);

                if ($status === 'present') {
                    $statusBadge = '<span class="badge bg-soft-success text-success">Present</span>';
                } elseif ($status === 'wfh') {
                    $statusBadge = '<span class="badge bg-soft-info text-info">WFH</span>';
                } elseif ($status === 'late') {
                    $statusBadge = '<span class="badge bg-soft-warning text-warning">Late</span>';
                } elseif ($status === 'half_day') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Half Day</span>';
                } elseif ($status === 'on_leave') {
                    $statusBadge = '<span class="badge bg-soft-primary text-primary">On Leave</span>';
                } elseif ($status === 'absent') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Absent</span>';
                } elseif ($status === 'under_hours') {
                    $statusBadge = '<span class="badge bg-soft-secondary text-secondary">Under Hours</span>';
                } else {
                    $statusBadge = '<span class="badge bg-soft-dark text-dark">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
                }

                $checkInDisplay = '-';
                $checkOutDisplay = '-';
                $workHoursDisplay = '-';

                if ($log->check_in) {
                    $checkInCarbon = \Carbon\Carbon::parse($log->check_in);
                    if ($isAbsentOrLeave || $checkInCarbon->format('H:i:s') === '00:00:00') {
                        $checkInDisplay = '-';
                    } else {
                        $checkInDisplay = $checkInCarbon->format('h:i A');
                    }
                }

                if ($log->check_out) {
                    $checkOutCarbon = \Carbon\Carbon::parse($log->check_out);
                    if ($isAbsentOrLeave || $checkOutCarbon->format('H:i:s') === '00:00:00') {
                        $checkOutDisplay = '-';
                    } else {
                        $checkOutDisplay = $checkOutCarbon->format('h:i A');
                    }
                }

                if (!$isAbsentOrLeave) {
                    $workHoursDisplay = $log->formatted_work_hours;
                }

                return [
                    'id' => $log->id,
                    'employee_name' => $log->employee?->display_name ?? 'Unknown',
                    'employee_code' => $log->employee?->employee_id ?? 'Unknown',
                    'department' => $log->employee?->department?->name ?? 'No Department',
                    'location_type' => $log->formatted_location_type,
                    'check_in' => $checkInDisplay,
                    'check_out' => $checkOutDisplay,
                    'breaks' => $breaksHtml,
                    'work_hours' => $workHoursDisplay,
                    'status' => $statusBadge,
                    'status_raw' => $status,
                    'has_overtime' => in_array($log->employee_id, $approvedOvertimeEmployeeIds),
                    'check_in_latitude' => $log->check_in_latitude,
                    'check_in_longitude' => $log->check_in_longitude,
                    'check_out_latitude' => $log->check_out_latitude,
                    'check_out_longitude' => $log->check_out_longitude,
                    'check_in_selfie_url' => $log->check_in_selfie_path ? asset('storage/' . $log->check_in_selfie_path) : null,
                    'check_out_selfie_url' => $log->check_out_selfie_path ? asset('storage/' . $log->check_out_selfie_path) : null,
                    'location_logs' => $log->locationLogs->map(fn($l) => [
                        'lat' => (float)$l->latitude,
                        'lng' => (float)$l->longitude,
                        'time' => $l->created_at ? $l->created_at->format('h:i A') : ''
                    ])->toArray(),
                ];
            }),
        ]);
    }

    public function create(Request $request)
    {
        // Auto-heal any negative work hours from previous calculations
        Attendance::where('total_work_hours', '<', 0)
            ->update([
                'total_work_hours' => \DB::raw('ABS(total_work_hours)')
            ]);

        $companies = \App\Domains\HRMS\Models\Company::where('status', true)->orderBy('company_name')->get();
        $businessUnits = \App\Domains\HRMS\Models\BusinessUnit::where('status', true)->orderBy('name')->get();
        $branches = \App\Domains\HRMS\Models\Branch::where('status', true)->orderBy('name')->get();
        $departments = \App\Domains\HRMS\Models\Department::where('status', true)->orderBy('name')->get();

        $employeeId = $request->input('employee_id');

        if ($employeeId) {
            // SINGLE EMPLOYEE MODE: edit multiple dates for one employee
            $employee = \App\Domains\HRMS\Models\Employee::findOrFail($employeeId);
            
            // Generate last 15 days in descending order (e.g. today down to 14 days ago)
            $dates = [];
            for ($i = 0; $i < 15; $i++) {
                $dates[] = \Carbon\Carbon::today()->subDays($i)->format('Y-m-d');
            }

            // Fetch existing attendances for this employee on these dates
            $attendances = Attendance::where('employee_id', $employeeId)
                ->whereIn('date', $dates)
                ->with('breaks')
                ->get()
                ->keyBy(function($item) {
                    return $item->date->format('Y-m-d');
                });

            return view('modules.hrms.attendance.create', compact(
                'companies',
                'businessUnits',
                'branches',
                'departments',
                'employee',
                'dates',
                'attendances'
            ));
        }

        // DATE MODE (default)
        $date = $request->input('date', \Carbon\Carbon::today()->format('Y-m-d'));

        // Always fetch all active employees to enable real-time dynamic client-side filtering
        $employees = \App\Domains\HRMS\Models\Employee::where('status', true)
            ->with(['department', 'attendances' => function($q) use ($date) {
                $q->where('date', $date);
            }])
            ->orderBy('full_name')
            ->get();

        return view('modules.hrms.attendance.create', compact(
            'companies',
            'businessUnits',
            'branches',
            'departments',
            'date',
            'employees'
        ));
    }

    public function storeManual(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if ($employeeId) {
            // SINGLE EMPLOYEE MODE: multiple dates for one employee
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'attendance' => 'required|array',
                'attendance.*.date' => 'required|date',
                'attendance.*.check_in' => 'nullable|string',
                'attendance.*.check_out' => 'nullable|string',
                'attendance.*.status' => 'required|string|in:auto,present,absent,late,half_day,on_leave,wfh',
            ]);

            $tenantId = auth()->user()?->tenant_id;
            $count = 0;

            foreach ($validated['attendance'] as $row) {
                $dateStr = $row['date'];
                $status = $row['status'];
                $checkInTime = $row['check_in'] ? trim($row['check_in']) : null;
                $checkOutTime = $row['check_out'] ? trim($row['check_out']) : null;

                // Constraint
                if (!$checkInTime && in_array($status, ['present', 'late', 'half_day', 'wfh'])) {
                    $employee = \App\Domains\HRMS\Models\Employee::find($employeeId);
                    $empName = $employee ? $employee->display_name : "Employee ID {$employeeId}";
                    return redirect()->back()->withInput()->with('error', "Employee '{$empName}' cannot be marked as " . ucfirst(str_replace('_', ' ', $status)) . " without a Check-in time on {$dateStr}.");
                }

                // Combine date and time
                $checkInDatetime = null;
                if ($checkInTime) {
                    $checkInDatetime = \Carbon\Carbon::parse($dateStr . ' ' . $checkInTime);
                } elseif (in_array($status, ['absent', 'on_leave'])) {
                    $checkInDatetime = \Carbon\Carbon::parse($dateStr . ' 00:00:00');
                }

                $checkOutDatetime = null;
                if ($checkOutTime) {
                    $checkOutDatetime = \Carbon\Carbon::parse($dateStr . ' ' . $checkOutTime);
                }

                // Calculate total work hours
                $workHours = 0.00;
                if ($checkInDatetime && $checkOutDatetime) {
                    if ($checkOutDatetime->greaterThan($checkInDatetime)) {
                        $workHours = round($checkInDatetime->diffInMinutes($checkOutDatetime, true) / 60.0, 2);
                    }
                }

                // Auto Detect Status Logic
                if ($status === 'auto') {
                    if (!$checkInDatetime) {
                        $hasLeave = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employeeId)
                            ->where('status', 'approved')
                            ->whereDate('start_date', '<=', $dateStr)
                            ->whereDate('end_date', '>=', $dateStr)
                            ->exists();

                        $status = $hasLeave ? 'on_leave' : 'absent';
                        $checkInDatetime = \Carbon\Carbon::parse($dateStr . ' 00:00:00');
                    } else {
                        $employee = \App\Domains\HRMS\Models\Employee::find($employeeId);
                        
                        $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                            ->where('status', 'approved')
                            ->whereDate('start_date', '<=', $dateStr)
                            ->whereDate('end_date', '>=', $dateStr)
                            ->exists();

                        $status = $hasWfh ? 'wfh' : 'present';

                        $resolvedShift = $employee ? $employee->resolveShiftForDate($dateStr) : null;
                        if ($resolvedShift) {
                            $shiftStartStr = $resolvedShift->start_time;
                            $shiftStart = \Carbon\Carbon::parse($dateStr . ' ' . $shiftStartStr);

                            if ($checkInDatetime->greaterThan($shiftStart)) {
                                $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                        $q->where('company_id', $employee->company_id)
                                          ->orWhereNull('company_id');
                                    })
                                    ->where('rule_type', 'late_arrival')
                                    ->where('status', true)
                                    ->orderByRaw('company_id IS NULL ASC')
                                    ->first();

                                $graceMinutes = $penaltyRule ? (int)$penaltyRule->grace_period_minutes : 15;
                                $diffMinutes = $checkInDatetime->diffInMinutes($shiftStart);

                                if ($diffMinutes > $graceMinutes) {
                                    $status = 'late';
                                }
                            }
                        }

                        if ($checkInDatetime && $checkOutDatetime && $workHours !== null && $employee) {
                            $underHoursRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                    $q->where('company_id', $employee->company_id)
                                      ->orWhereNull('company_id');
                                })
                                ->where('rule_type', 'under_hours')
                                ->where('status', true)
                                ->orderByRaw('company_id IS NULL ASC')
                                ->first();

                            if ($underHoursRule && is_array($underHoursRule->penalty_tiers)) {
                                $sortedTiers = collect($underHoursRule->penalty_tiers)->sortBy('hours_threshold')->all();
                                foreach ($sortedTiers as $tier) {
                                    $hoursThreshold = isset($tier['hours_threshold']) ? floatval($tier['hours_threshold']) : 0;
                                    if ($workHours < $hoursThreshold) {
                                        $action = $tier['penalty_action'] ?? '';
                                        $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;
                                        if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                                            if ($val >= 1.0) {
                                                $status = 'absent';
                                            } elseif ($val > 0) {
                                                $status = 'half_day';
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                $locationType = ($status === 'wfh') ? 'wfh' : 'office';

                Attendance::updateOrCreate([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employeeId,
                    'date' => $dateStr,
                ], [
                    'check_in' => $checkInDatetime,
                    'check_out' => $checkOutDatetime,
                    'location_type' => $locationType,
                    'status' => $status,
                    'total_work_hours' => $workHours,
                ]);

                $count++;
            }

            $redirectUrl = $request->input('redirect_url');
            if (!empty($redirectUrl)) {
                $host = parse_url($redirectUrl, PHP_URL_HOST);
                if (!$host || $host === $request->getHost()) {
                    return redirect($redirectUrl)->with('success', "Manually updated attendance.");
                }
            }

            return redirect()->route('hrms.attendance.index', ['view' => 'employee'])
                ->with('success', "Manually updated attendance.");
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*.employee_id' => 'required|exists:employees,id',
            'attendance.*.check_in' => 'nullable|string',
            'attendance.*.check_out' => 'nullable|string',
            'attendance.*.status' => 'required|string|in:auto,present,absent,late,half_day,on_leave,wfh',
        ]);

        $dateStr = $validated['date'];
        $tenantId = auth()->user()?->tenant_id;
        $count = 0;

        foreach ($validated['attendance'] as $row) {
            $employeeId = $row['employee_id'];
            $status = $row['status'];
            $checkInTime = $row['check_in'] ? trim($row['check_in']) : null;
            $checkOutTime = $row['check_out'] ? trim($row['check_out']) : null;

            // Constraint: "suppose some one punches are not there but they show the present in status its not allow"
            if (!$checkInTime && in_array($status, ['present', 'late', 'half_day', 'wfh'])) {
                $employee = \App\Domains\HRMS\Models\Employee::find($employeeId);
                $empName = $employee ? $employee->display_name : "Employee ID {$employeeId}";
                return redirect()->back()->withInput()->with('error', "Employee '{$empName}' cannot be marked as " . ucfirst(str_replace('_', ' ', $status)) . " without a Check-in time.");
            }

            // Combine date and time
            $checkInDatetime = null;
            if ($checkInTime) {
                $checkInDatetime = \Carbon\Carbon::parse($dateStr . ' ' . $checkInTime);
            } elseif (in_array($status, ['absent', 'on_leave'])) {
                // Check-in is NOT NULL in database; store midnight as standard placeholder
                $checkInDatetime = \Carbon\Carbon::parse($dateStr . ' 00:00:00');
            }

            $checkOutDatetime = null;
            if ($checkOutTime) {
                $checkOutDatetime = \Carbon\Carbon::parse($dateStr . ' ' . $checkOutTime);
            }

            // Calculate total work hours
            $workHours = 0.00;
            if ($checkInDatetime && $checkOutDatetime) {
                if ($checkOutDatetime->greaterThan($checkInDatetime)) {
                    $workHours = round($checkInDatetime->diffInMinutes($checkOutDatetime, true) / 60.0, 2);
                }
            }

            // Auto Detect Status Logic
            if ($status === 'auto') {
                if (!$checkInDatetime) {
                    // Check if employee has an approved leave request on this date
                    $hasLeave = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employeeId)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $dateStr)
                        ->whereDate('end_date', '>=', $dateStr)
                        ->exists();

                    $status = $hasLeave ? 'on_leave' : 'absent';
                    $checkInDatetime = \Carbon\Carbon::parse($dateStr . ' 00:00:00');
                } else {
                    $employee = \App\Domains\HRMS\Models\Employee::find($employeeId);
                    
                    // Check if employee has an approved WFH request on this date
                    $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $dateStr)
                        ->whereDate('end_date', '>=', $dateStr)
                        ->exists();

                    $status = $hasWfh ? 'wfh' : 'present';

                    $resolvedShift = $employee ? $employee->resolveShiftForDate($dateStr) : null;
                    if ($resolvedShift) {
                        $shiftStartStr = $resolvedShift->start_time; // e.g. "09:00:00"
                        $shiftStart = \Carbon\Carbon::parse($dateStr . ' ' . $shiftStartStr);

                        if ($checkInDatetime->greaterThan($shiftStart)) {
                            // Find grace period configurations
                            $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                    $q->where('company_id', $employee->company_id)
                                      ->orWhereNull('company_id');
                                })
                                ->where('rule_type', 'late_arrival')
                                ->where('status', true)
                                ->orderByRaw('company_id IS NULL ASC')
                                ->first();

                            $graceMinutes = $penaltyRule ? (int)$penaltyRule->grace_period_minutes : 15;
                            $diffMinutes = $checkInDatetime->diffInMinutes($shiftStart);

                            if ($diffMinutes > $graceMinutes) {
                                $status = 'late';
                            }
                        }
                    }

                    // Check work hours for deficit penalties (under hours / half day)
                    if ($checkInDatetime && $checkOutDatetime && $workHours !== null && $employee) {
                        $underHoursRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                $q->where('company_id', $employee->company_id)
                                  ->orWhereNull('company_id');
                            })
                            ->where('rule_type', 'under_hours')
                            ->where('status', true)
                            ->orderByRaw('company_id IS NULL ASC')
                            ->first();

                        if ($underHoursRule && is_array($underHoursRule->penalty_tiers)) {
                            $sortedTiers = collect($underHoursRule->penalty_tiers)->sortBy('hours_threshold')->all();
                            foreach ($sortedTiers as $tier) {
                                $hoursThreshold = isset($tier['hours_threshold']) ? floatval($tier['hours_threshold']) : 0;
                                if ($workHours < $hoursThreshold) {
                                    $action = $tier['penalty_action'] ?? '';
                                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;
                                    if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                                        if ($val >= 1.0) {
                                            $status = 'absent';
                                        } elseif ($val > 0) {
                                            $status = 'half_day';
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            $locationType = ($status === 'wfh') ? 'wfh' : 'office';

            // Find or create attendance
            $attendance = Attendance::updateOrCreate([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'date' => $dateStr,
            ], [
                'check_in' => $checkInDatetime,
                'check_out' => $checkOutDatetime,
                'location_type' => $locationType,
                'status' => $status,
                'total_work_hours' => $workHours,
            ]);

            $count++;
        }

        $redirectUrl = $request->input('redirect_url');
        if (!empty($redirectUrl)) {
            $host = parse_url($redirectUrl, PHP_URL_HOST);
            if (!$host || $host === $request->getHost()) {
                return redirect($redirectUrl)->with('success', "Manually updated attendance.");
            }
        }

        return redirect()->route('hrms.attendance.index', ['date' => $dateStr])
            ->with('success', "Manually updated attendance.");
    }

    public function destroyDate($date)
    {
        $attendances = Attendance::where('date', $date)->get();
        foreach ($attendances as $attendance) {
            $attendance->breaks()->delete();
            $attendance->delete();
        }

        return redirect()->back()->with('success', 'Attendance logs deleted successfully.');
    }

    public function export(Request $request)
    {
        $filters = $request->only(['date', 'department_id', 'search', 'status']);
        
        $query = Attendance::with(['employee.department']);

        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        }
        if (!empty($filters['department_id'])) {
            $query->whereHas('employee', function($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }
        if (!empty($filters['search'])) {
            $query->whereHas('employee', function($q) use ($filters) {
                $q->where('full_name', 'like', "%{$filters['search']}%")
                  ->orWhere('employee_id', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $logs = $query->orderBy('date', 'desc')
            ->orderByRaw('(select full_name from employees where employees.id = attendances.employee_id) ASC')
            ->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=attendance_export_" . date('Ymd_His') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Employee Code', 'Employee Name', 'Department', 'Date', 'Check In', 'Check Out', 'Status', 'Location Type', 'Total Work Hours'];

        $callback = function() use($logs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->employee?->employee_id ?? '',
                    $log->employee?->display_name ?? '',
                    $log->employee?->department?->name ?? '',
                    $log->date->format('Y-m-d'),
                    $log->check_in ? $log->check_in->format('H:i:s') : '',
                    $log->check_out ? $log->check_out->format('H:i:s') : '',
                    $log->status ?: 'present',
                    $log->location_type ?: 'office',
                    $log->total_work_hours ?: 0.00
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadTemplate()
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=attendance_import_template.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['employee_code', 'date', 'check_in', 'check_out', 'status'];

        $callback = function() use($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            
            // Add sample rows
            fputcsv($file, ['EMP-0001', '2026-08-05', '09:00', '18:00', 'auto']);
            fputcsv($file, ['EMP-0002', '2026-08-05', '', '', 'on_leave']);
            fputcsv($file, ['EMP-0003', '2026-08-05', '09:30', '17:30', 'wfh']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle); // Read headers

        if (!$header) {
            fclose($handle);
            return redirect()->back()->with('error', 'The uploaded file is empty.');
        }

        // Map header column indices
        $header = array_map('strtolower', $header);
        $colIndex = [
            'employee_code' => array_search('employee_code', $header),
            'date' => array_search('date', $header),
            'check_in' => array_search('check_in', $header),
            'check_out' => array_search('check_out', $header),
            'status' => array_search('status', $header),
        ];

        if ($colIndex['employee_code'] === false || $colIndex['date'] === false) {
            fclose($handle);
            return redirect()->back()->with('error', 'Invalid template. "employee_code" and "date" columns are required.');
        }

        $tenantId = auth()->user()?->tenant_id;
        $successCount = 0;
        $skippedRows = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            
            // Basic check for empty row
            if (empty($row) || count($row) < 2) {
                continue;
            }

            $empCode = trim($row[$colIndex['employee_code']] ?? '');
            $dateStr = trim($row[$colIndex['date']] ?? '');

            if (!$empCode || !$dateStr) {
                $skippedRows[] = "Row {$rowNum}: Missing employee_code or date.";
                continue;
            }

            // Find employee
            $employee = \App\Domains\HRMS\Models\Employee::where('employee_id', $empCode)->first();
            if (!$employee) {
                $skippedRows[] = "Row {$rowNum}: Employee code '{$empCode}' not found.";
                continue;
            }

            // Parse Date
            try {
                $carbonDate = \Carbon\Carbon::parse($dateStr);
                $formattedDate = $carbonDate->format('Y-m-d');
            } catch (\Exception $e) {
                $skippedRows[] = "Row {$rowNum}: Invalid date format '{$dateStr}'.";
                continue;
            }

            // Extract values
            $checkInVal = $colIndex['check_in'] !== false && !empty($row[$colIndex['check_in']]) ? trim($row[$colIndex['check_in']]) : null;
            $checkOutVal = $colIndex['check_out'] !== false && !empty($row[$colIndex['check_out']]) ? trim($row[$colIndex['check_out']]) : null;
            $statusVal = $colIndex['status'] !== false && !empty($row[$colIndex['status']]) ? strtolower(trim($row[$colIndex['status']])) : 'auto';

            // Validate status enum
            $validStatuses = ['auto', 'present', 'absent', 'late', 'half_day', 'on_leave', 'wfh'];
            if (!in_array($statusVal, $validStatuses)) {
                $statusVal = 'auto';
            }

            // Check constraint
            if (!$checkInVal && in_array($statusVal, ['present', 'late', 'half_day', 'wfh'])) {
                $skippedRows[] = "Row {$rowNum}: Cannot mark employee '{$employee->display_name}' as " . ucfirst(str_replace('_', ' ', $statusVal)) . " without check_in time.";
                continue;
            }

            // Combine date and time
            $checkInDatetime = null;
            if ($checkInVal) {
                try {
                    $checkInDatetime = \Carbon\Carbon::parse($formattedDate . ' ' . $checkInVal);
                } catch (\Exception $e) {
                    $skippedRows[] = "Row {$rowNum}: Invalid check_in time '{$checkInVal}'.";
                    continue;
                }
            } elseif (in_array($statusVal, ['absent', 'on_leave'])) {
                $checkInDatetime = \Carbon\Carbon::parse($formattedDate . ' 00:00:00');
            }

            $checkOutDatetime = null;
            if ($checkOutVal) {
                try {
                    $checkOutDatetime = \Carbon\Carbon::parse($formattedDate . ' ' . $checkOutVal);
                } catch (\Exception $e) {
                    $skippedRows[] = "Row {$rowNum}: Invalid check_out time '{$checkOutVal}'.";
                    continue;
                }
            }

            // Calculate work hours
            $workHours = 0.00;
            if ($checkInDatetime && $checkOutDatetime) {
                if ($checkOutDatetime->greaterThan($checkInDatetime)) {
                    $workHours = round($checkInDatetime->diffInMinutes($checkOutDatetime, true) / 60.0, 2);
                }
            }

            // Auto Detect Status Logic
            $finalStatus = $statusVal;
            if ($finalStatus === 'auto') {
                if (!$checkInDatetime) {
                    $hasLeave = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $formattedDate)
                        ->whereDate('end_date', '>=', $formattedDate)
                        ->exists();

                    $finalStatus = $hasLeave ? 'on_leave' : 'absent';
                    $checkInDatetime = \Carbon\Carbon::parse($formattedDate . ' 00:00:00');
                } else {
                    $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employee->id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $formattedDate)
                        ->whereDate('end_date', '>=', $formattedDate)
                        ->exists();

                    $finalStatus = $hasWfh ? 'wfh' : 'present';

                    $resolvedShift = $employee->resolveShiftForDate($formattedDate);
                    if ($resolvedShift) {
                        $shiftStart = \Carbon\Carbon::parse($formattedDate . ' ' . $resolvedShift->start_time);
                        if ($checkInDatetime->greaterThan($shiftStart)) {
                            $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                    $q->where('company_id', $employee->company_id)
                                      ->orWhereNull('company_id');
                                })
                                ->where('rule_type', 'late_arrival')
                                ->where('status', true)
                                ->orderByRaw('company_id IS NULL ASC')
                                ->first();

                            $graceMinutes = $penaltyRule ? (int)$penaltyRule->grace_period_minutes : 15;
                            $diffMinutes = $checkInDatetime->diffInMinutes($shiftStart);

                            if ($diffMinutes > $graceMinutes) {
                                $finalStatus = 'late';
                            }
                        }
                    }

                    if ($checkInDatetime && $checkOutDatetime && $workHours !== null) {
                        $underHoursRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                $q->where('company_id', $employee->company_id)
                                  ->orWhereNull('company_id');
                            })
                            ->where('rule_type', 'under_hours')
                            ->where('status', true)
                            ->orderByRaw('company_id IS NULL ASC')
                            ->first();

                        if ($underHoursRule && is_array($underHoursRule->penalty_tiers)) {
                            $sortedTiers = collect($underHoursRule->penalty_tiers)->sortBy('hours_threshold')->all();
                            foreach ($sortedTiers as $tier) {
                                $hoursThreshold = isset($tier['hours_threshold']) ? floatval($tier['hours_threshold']) : 0;
                                if ($workHours < $hoursThreshold) {
                                    $action = $tier['penalty_action'] ?? '';
                                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;
                                    if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                                        if ($val >= 1.0) {
                                            $finalStatus = 'absent';
                                        } elseif ($val > 0) {
                                            $finalStatus = 'half_day';
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            $locationType = ($finalStatus === 'wfh') ? 'wfh' : 'office';

            Attendance::updateOrCreate([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'date' => $formattedDate,
            ], [
                'check_in' => $checkInDatetime,
                'check_out' => $checkOutDatetime,
                'location_type' => $locationType,
                'status' => $finalStatus,
                'total_work_hours' => $workHours,
            ]);

            $successCount++;
        }

        fclose($handle);

        $msg = "Attendance logs imported successfully.";
        if (count($skippedRows) > 0) {
            return redirect()->back()
                ->with('success', $msg)
                ->withErrors($skippedRows);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function trackLocation(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
        ]);

        $result = $this->attendanceRepository->trackLocation(
            (int) $validated['employee_id'],
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        return response()->json($result);
    }
}
