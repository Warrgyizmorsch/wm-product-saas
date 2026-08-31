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
        $filters = $request->only(['date', 'department_id', 'search', 'status', 'month']);
        
        $date = $request->input('date');
        if (!$date && !$request->has('month')) {
            $date = \Carbon\Carbon::today()->format('Y-m-d');
        }
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
            $monthFilter = $filters['month'] ?? null;
            if (!$monthFilter && !isset($filters['date'])) {
                $monthFilter = now()->format('Y-m');
            }

            $year = now()->year;
            $month = now()->month;
            if ($monthFilter) {
                $parts = explode('-', $monthFilter);
                if (count($parts) === 2) {
                    $year = (int)$parts[0];
                    $month = (int)$parts[1];
                }
            }

            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
            $today = now()->startOfDay();
            if ($endDate->gt($today)) {
                $endDate = $today->copy();
            }

            if (isset($filters['date']) && $filters['date']) {
                $startDate = \Carbon\Carbon::parse($filters['date'])->startOfDay();
                $endDate = $startDate->copy()->endOfDay();
            }

            $calendarDates = [];
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dayObj = new \stdClass();
                $dayObj->date = $currentDate->copy();
                $calendarDates[] = $dayObj;
                $currentDate->addDay();
            }

            // Apply filters
            if ($search || $departmentId || $statusFilter) {
                $filteredDates = [];
                foreach ($calendarDates as $day) {
                    $dStr = $day->date->format('Y-m-d');
                    $query = Attendance::where('date', $dStr);
                    
                    if ($search) {
                        $query->whereIn('employee_id', function($sq) use ($search) {
                            $sq->select('id')
                              ->from('employees')
                              ->where('full_name', 'like', "%{$search}%")
                              ->orWhere('employee_id', 'like', "%{$search}%");
                        });
                    }
                    
                    if ($departmentId) {
                        $query->whereIn('employee_id', function($sq) use ($departmentId) {
                            $sq->select('id')
                              ->from('employees')
                              ->where('department_id', $departmentId);
                        });
                    }
                    
                    if ($statusFilter) {
                        $query->where('status', $statusFilter);
                    }
                    
                    if ($query->exists()) {
                        $filteredDates[] = $day;
                    }
                }
                $calendarDates = $filteredDates;
            }

            // Sort descending
            usort($calendarDates, function($a, $b) {
                return $b->date->timestamp <=> $a->date->timestamp;
            });

            $dates = collect($calendarDates);

            $statsByDate = collect();
            if ($dates->isNotEmpty()) {
                $dateStrings = $dates->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->toArray();
                
                // Fetch active employees
                $allEmployees = \App\Domains\HRMS\Models\Employee::where('status', true)->get();
                
                // Fetch attendances for these dates
                $allAttendances = Attendance::whereIn('date', $dateStrings)
                    ->get()
                    ->groupBy(fn($a) => $a->date->format('Y-m-d'));
                
                // Fetch holidays overlapping these dates
                $minDate = min($dateStrings);
                $maxDate = max($dateStrings);
                $allHolidays = \App\Domains\HRMS\Models\HolidayCalendar::where('status', true)
                    ->whereBetween('holiday_date', [$minDate, $maxDate])
                    ->get();
                
                // Fetch approved leaves overlapping these dates
                $allLeaves = \App\Domains\HRMS\Models\LeaveRequest::where('status', 'approved')
                    ->where(function($q) use ($minDate, $maxDate) {
                        $q->whereBetween('start_date', [$minDate, $maxDate])
                          ->orWhereBetween('end_date', [$minDate, $maxDate])
                          ->orWhere(function($sub) use ($minDate, $maxDate) {
                              $sub->where('start_date', '<=', $minDate)
                                  ->where('end_date', '>=', $maxDate);
                          });
                    })
                    ->get();

                // Compute stats for each date
                $computedStats = [];
                foreach ($dateStrings as $dStr) {
                    $carbonDate = \Carbon\Carbon::parse($dStr);
                    $dateAttendances = $allAttendances->get($dStr, collect())->keyBy('employee_id');
                    
                    $present = 0;
                    $late = 0;
                    $half_day = 0;
                    $wfh = 0;
                    $absent = 0;
                    $counts = [
                        'present' => 0, 'wfh' => 0, 'late' => 0, 'half_day' => 0,
                        'absent' => 0, 'on_leave' => 0, 'week_off' => 0, 'holiday' => 0
                    ];

                    foreach ($allEmployees as $emp) {
                        $att = $dateAttendances->get($emp->id);
                        $res = $this->resolveStatusForDate($emp, $carbonDate, $att, $allHolidays, $allLeaves);
                        
                        $status = $res['status'];
                        if (isset($counts[$status])) {
                            $counts[$status]++;
                        }
                    }

                    $computedStats[$dStr] = $counts;
                }
                
                $statsByDate = collect($computedStats);
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
        $queryDate = $date ?? \Carbon\Carbon::today()->format('Y-m-d');
        $query = \App\Domains\HRMS\Models\Employee::where('status', true)
            ->with(['department', 'designation', 'attendances' => function($q) use ($queryDate) {
                $q->where('date', $queryDate)->with('breaks');
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
            $query->whereHas('attendances', function($q) use ($queryDate, $statusFilter) {
                $q->where('date', $queryDate)->where('status', $statusFilter);
            });
        }

        // Sorting logic
        if ($sort === 'name_desc') {
            $query->orderBy('full_name', 'desc');
        } elseif ($sort === 'checkin_asc' || $sort === 'checkin_desc') {
            $order = ($sort === 'checkin_desc') ? 'desc' : 'asc';
            $query->select('employees.*')
                ->leftJoin('attendances', function($join) use ($queryDate) {
                    $join->on('employees.id', '=', 'attendances.employee_id')
                         ->where('attendances.date', '=', $queryDate);
                })
                ->orderByRaw("CASE WHEN attendances.check_in IS NULL THEN 1 ELSE 0 END, attendances.check_in {$order}");
        } else {
            $query->orderBy('full_name', 'asc');
        }

        $employees = $query->get();
        
        $monthFilter = $filters['month'] ?? null;
        if (!$monthFilter && !isset($filters['date'])) {
            $monthFilter = now()->format('Y-m');
        }

        $year = now()->year;
        $month = now()->month;
        if ($monthFilter) {
            $parts = explode('-', $monthFilter);
            if (count($parts) === 2) {
                $year = (int)$parts[0];
                $month = (int)$parts[1];
            }
        }

        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $today = now()->startOfDay();
        if ($endDate->gt($today)) {
            $endDate = $today->copy();
        }

        if ($employees->isNotEmpty()) {
            $employeeIds = $employees->pluck('id')->toArray();

            // Fetch attendances for these employees for this month
            $monthAttendances = Attendance::whereIn('employee_id', $employeeIds)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->groupBy('employee_id');

            // Fetch holidays for this month
            $monthHolidays = \App\Domains\HRMS\Models\HolidayCalendar::where('status', true)
                ->whereBetween('holiday_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            // Fetch approved leaves for these employees for this month
            $monthLeaves = \App\Domains\HRMS\Models\LeaveRequest::whereIn('employee_id', $employeeIds)
                ->where('status', 'approved')
                ->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                      ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                      ->orWhere(function($sub) use ($startDate, $endDate) {
                          $sub->where('start_date', '<=', $startDate->format('Y-m-d'))
                              ->where('end_date', '>=', $endDate->format('Y-m-d'));
                      });
                })
                ->get()
                ->groupBy('employee_id');

            foreach ($employees as $employee) {
                $empAttendances = $monthAttendances->get($employee->id, collect())->keyBy(function($att) {
                    return $att->date->format('Y-m-d');
                });
                $empLeaves = $monthLeaves->get($employee->id, collect());

                $counts = [
                    'present' => 0, 'wfh' => 0, 'late' => 0, 'half_day' => 0,
                    'absent' => 0, 'on_leave' => 0, 'week_off' => 0, 'holiday' => 0
                ];

                $joiningDate = $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining)->startOfDay() : null;
                $empStartDate = $startDate->copy();
                if ($joiningDate && $joiningDate->gt($empStartDate)) {
                    $empStartDate = $joiningDate->copy();
                }
                
                $empEndDate = $endDate->copy();
                if ($empStartDate->gt($empEndDate)) {
                    $employee->computed_present = 0;
                    $employee->computed_late = 0;
                    $employee->computed_wfh = 0;
                    $employee->computed_half_day = 0;
                    $employee->computed_absent = 0;
                    $employee->computed_on_leave = 0;
                    $employee->computed_week_off = 0;
                    $employee->computed_holiday = 0;
                    continue;
                }

                $currentDate = $empStartDate->copy();
                while ($currentDate->lte($empEndDate)) {
                    $dateStr = $currentDate->format('Y-m-d');
                    $att = $empAttendances->get($dateStr);
                    $res = $this->resolveStatusForDate($employee, $currentDate, $att, $monthHolidays, $empLeaves);

                    $status = $res['status'];
                    if (isset($counts[$status])) {
                        $counts[$status]++;
                    }
                    $currentDate->addDay();
                }

                $employee->computed_present = $counts['present'];
                $employee->computed_late = $counts['late'];
                $employee->computed_wfh = $counts['wfh'];
                $employee->computed_half_day = $counts['half_day'];
                $employee->computed_absent = $counts['absent'];
                $employee->computed_on_leave = $counts['on_leave'];
                $employee->computed_week_off = $counts['week_off'];
                $employee->computed_holiday = $counts['holiday'];
            }
        }

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

        $year = now()->year;
        $month = now()->month;
        if ($monthFilter) {
            $parts = explode('-', $monthFilter);
            if (count($parts) === 2) {
                $year = (int)$parts[0];
                $month = (int)$parts[1];
            }
        }
        
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $joiningDate = $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining)->startOfDay() : null;
        if ($joiningDate && $joiningDate->gt($startDate)) {
            $startDate = $joiningDate->copy();
        }

        $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $today = now()->startOfDay();
        if ($endDate->gt($today)) {
            $endDate = $today->copy();
        }

        if ($date) {
            $parsedDate = \Carbon\Carbon::parse($date)->startOfDay();
            if ($joiningDate && $joiningDate->gt($parsedDate)) {
                $startDate = $joiningDate->copy();
                $endDate = $parsedDate->copy()->endOfDay();
            } else {
                $startDate = $parsedDate;
                $endDate = $parsedDate->copy()->endOfDay();
            }
        }

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['breaks', 'locationLogs'])
            ->get();

        $holidays = \App\Domains\HRMS\Models\HolidayCalendar::where('status', true)
            ->whereBetween('holiday_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $leaves = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $startDate->format('Y-m-d'))
                          ->where('end_date', '>=', $endDate->format('Y-m-d'));
                  });
            })
            ->with('leaveType')
            ->get();

        $attendancesByDate = $attendances->keyBy(function($att) {
            return $att->date->format('Y-m-d');
        });

        $calendarDays = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $attRecord = $attendancesByDate->get($dateStr);
            
            $resolved = $this->resolveStatusForDate(
                $employee,
                $currentDate,
                $attRecord,
                $holidays,
                $leaves
            );
            
            $dayObj = new \stdClass();
            $dayObj->id = $attRecord ? $attRecord->id : null;
            $dayObj->date = $currentDate->copy();
            $dayObj->location_type = $attRecord ? $attRecord->location_type : '';
            $dayObj->formatted_location_type = $attRecord ? $attRecord->formatted_location_type : '—';
            $dayObj->check_in = $attRecord ? $attRecord->check_in : null;
            $dayObj->check_in_latitude = $attRecord ? $attRecord->check_in_latitude : null;
            $dayObj->check_out = $attRecord ? $attRecord->check_out : null;
            $dayObj->check_out_latitude = $attRecord ? $attRecord->check_out_latitude : null;
            $dayObj->breaks = $attRecord ? $attRecord->breaks : collect();
            $dayObj->total_break_hours = $attRecord ? $attRecord->total_break_hours : 0;
            $dayObj->formatted_break_hours = $attRecord ? $attRecord->formatted_break_hours : '—';
            $dayObj->formatted_work_hours = $attRecord ? $attRecord->formatted_work_hours : '—';
            
            $dayObj->status = $resolved['status'];
            $dayObj->status_label = $resolved['label'];
            $dayObj->holiday_name = $resolved['holiday_name'];
            $dayObj->leave_type_name = $resolved['leave_type_name'];
            
            $dayObj->check_in_selfie_path = $attRecord ? $attRecord->check_in_selfie_path : null;
            $dayObj->check_out_selfie_path = $attRecord ? $attRecord->check_out_selfie_path : null;
            $dayObj->check_in_longitude = $attRecord ? $attRecord->check_in_longitude : null;
            $dayObj->check_out_longitude = $attRecord ? $attRecord->check_out_longitude : null;
            $dayObj->locationLogs = $attRecord ? $attRecord->locationLogs : collect();

            $calendarDays[] = $dayObj;
            
            $currentDate->addDay();
        }

        $calendarDays = collect($calendarDays);

        if ($status) {
            $calendarDays = $calendarDays->filter(function($day) use ($status) {
                return $day->status === $status;
            });
        }

        if ($search) {
            $searchLower = strtolower($search);
            $calendarDays = $calendarDays->filter(function($day) use ($searchLower) {
                return str_contains(strtolower($day->date->format('M d, Y')), $searchLower) ||
                       str_contains(strtolower($day->status_label), $searchLower) ||
                       str_contains(strtolower($day->location_type), $searchLower) ||
                       ($day->holiday_name && str_contains(strtolower($day->holiday_name), $searchLower)) ||
                       ($day->leave_type_name && str_contains(strtolower($day->leave_type_name), $searchLower));
            });
        }

        if ($sort === 'date_asc') {
            $calendarDays = $calendarDays->sortBy(fn($d) => $d->date->timestamp);
        } elseif ($sort === 'checkin_asc') {
            $calendarDays = $calendarDays->sortBy(function($d) {
                return $d->check_in ? $d->check_in->timestamp : PHP_INT_MAX;
            });
        } elseif ($sort === 'checkin_desc') {
            $calendarDays = $calendarDays->sortByDesc(function($d) {
                return $d->check_in ? $d->check_in->timestamp : 0;
            });
        } else {
            $calendarDays = $calendarDays->sortByDesc(fn($d) => $d->date->timestamp);
        }

        $attendances = $calendarDays;

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
        $monthFilter = request('month', now()->format('Y-m'));
        $year = now()->year;
        $month = now()->month;
        if ($monthFilter) {
            $parts = explode('-', $monthFilter);
            if (count($parts) === 2) {
                $year = (int)$parts[0];
                $month = (int)$parts[1];
            }
        }
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $joiningDate = $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining)->startOfDay() : null;
        if ($joiningDate && $joiningDate->gt($startDate)) {
            $startDate = $joiningDate->copy();
        }

        $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $today = now()->startOfDay();
        if ($endDate->gt($today)) {
            $endDate = $today->copy();
        }

        $logs = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['breaks', 'locationLogs'])
            ->get();

        $holidays = \App\Domains\HRMS\Models\HolidayCalendar::where('status', true)
            ->whereBetween('holiday_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $leaves = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $startDate->format('Y-m-d'))
                          ->where('end_date', '>=', $endDate->format('Y-m-d'));
                  });
            })
            ->with('leaveType')
            ->get();

        $approvedOvertimeDates = \App\Domains\HRMS\Models\OvertimeRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->pluck('date')
            ->map(function ($d) {
                return $d->format('Y-m-d');
            })
            ->toArray();

        $attendancesByDate = $logs->keyBy(function($att) {
            return $att->date->format('Y-m-d');
        });

        $calendarDays = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $attRecord = $attendancesByDate->get($dateStr);
            
            $resolved = $this->resolveStatusForDate(
                $employee,
                $currentDate,
                $attRecord,
                $holidays,
                $leaves
            );
            
            $log = new \stdClass();
            $log->id = $attRecord ? $attRecord->id : 'virtual_' . $currentDate->timestamp;
            $log->date = $currentDate->format('M d, Y');
            $log->formatted_location_type = $attRecord ? $attRecord->formatted_location_type : '—';
            $log->location_type = $attRecord ? $attRecord->location_type : '';
            $log->check_in = $attRecord ? $attRecord->check_in : null;
            $log->check_out = $attRecord ? $attRecord->check_out : null;
            $log->breaks = $attRecord ? $attRecord->breaks : collect();
            $log->total_break_hours = $attRecord ? $attRecord->total_break_hours : 0;
            $log->formatted_break_hours = $attRecord ? $attRecord->formatted_break_hours : '—';
            $log->formatted_work_hours = $attRecord ? $attRecord->formatted_work_hours : '—';
            
            $log->status = $resolved['status'];
            $log->status_label = $resolved['label'];
            $log->holiday_name = $resolved['holiday_name'];
            $log->leave_type_name = $resolved['leave_type_name'];
            $log->check_in_latitude = $attRecord ? $attRecord->check_in_latitude : null;
            $log->check_in_longitude = $attRecord ? $attRecord->check_in_longitude : null;
            $log->check_out_latitude = $attRecord ? $attRecord->check_out_latitude : null;
            $log->check_out_longitude = $attRecord ? $attRecord->check_out_longitude : null;
            $log->check_in_selfie_path = $attRecord ? $attRecord->check_in_selfie_path : null;
            $log->check_out_selfie_path = $attRecord ? $attRecord->check_out_selfie_path : null;
            $log->locationLogs = $attRecord ? $attRecord->locationLogs : collect();

            $calendarDays[] = $log;
            
            $currentDate->addDay();
        }

        // Sort descending
        usort($calendarDays, function($a, $b) {
            return strcmp(\Carbon\Carbon::parse($b->date)->format('Y-m-d'), \Carbon\Carbon::parse($a->date)->format('Y-m-d'));
        });

        $calendarDays = collect($calendarDays);

        return response()->json([
            'employee' => [
                'name' => $employee->display_name,
                'code' => $employee->employee_id,
            ],
            'logs' => $calendarDays->map(function ($log) use ($approvedOvertimeDates) {
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

                $status = $log->status;
                $isAbsentOrLeave = in_array($status, ['absent', 'on_leave', 'week_off', 'holiday']);

                if ($status === 'present') {
                    $statusBadge = '<span class="badge bg-soft-success text-success">Present</span>';
                } elseif ($status === 'wfh') {
                    $statusBadge = '<span class="badge bg-soft-info text-info">WFH</span>';
                } elseif ($status === 'late') {
                    $statusBadge = '<span class="badge bg-soft-warning text-warning">Late</span>';
                } elseif ($status === 'half_day') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Half Day</span>';
                } elseif ($status === 'on_leave') {
                    $statusBadge = '<span class="badge bg-soft-primary text-primary">On Leave: ' . ($log->leave_type_name ?? 'Leave') . '</span>';
                } elseif ($status === 'absent') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Absent</span>';
                } elseif ($status === 'week_off') {
                    $statusBadge = '<span class="badge bg-soft-secondary text-secondary">Week Off</span>';
                } elseif ($status === 'holiday') {
                    $statusBadge = '<span class="badge bg-soft-indigo text-indigo" style="background-color: rgba(79, 70, 229, 0.1); color: #4f46e5 !important;">Holiday: ' . ($log->holiday_name ?? '') . '</span>';
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
                    'date' => $log->date,
                    'location_type' => $log->formatted_location_type,
                    'check_in' => $checkInDisplay,
                    'check_out' => $checkOutDisplay,
                    'breaks' => $breaksHtml,
                    'work_hours' => $workHoursDisplay,
                    'status' => $statusBadge,
                    'status_raw' => $status,
                    'has_overtime' => in_array(\Carbon\Carbon::parse($log->date)->format('Y-m-d'), $approvedOvertimeDates),
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
        $carbonDate = \Carbon\Carbon::parse($date);
        $dateStr = $carbonDate->format('Y-m-d');

        $employees = \App\Domains\HRMS\Models\Employee::where('status', true)
            ->with(['department'])
            ->get();

        $holidays = \App\Domains\HRMS\Models\HolidayCalendar::where('status', true)
            ->where('holiday_date', $dateStr)
            ->get();

        $leaves = \App\Domains\HRMS\Models\LeaveRequest::where('status', 'approved')
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->with('leaveType')
            ->get();

        $attendances = Attendance::where('date', $dateStr)
            ->with(['breaks', 'locationLogs'])
            ->get()
            ->keyBy('employee_id');

        $approvedOvertimeEmployeeIds = \App\Domains\HRMS\Models\OvertimeRequest::where('date', $dateStr)
            ->where('status', 'approved')
            ->pluck('employee_id')
            ->toArray();

        $logs = $employees->map(function ($employee) use ($carbonDate, $attendances, $holidays, $leaves) {
            $attRecord = $attendances->get($employee->id);
            $resolved = $this->resolveStatusForDate($employee, $carbonDate, $attRecord, $holidays, $leaves);

            $log = new \stdClass();
            $log->id = $attRecord ? $attRecord->id : 'virtual_' . $employee->id;
            $log->employee = $employee;
            $log->employee_name = $employee->display_name;
            $log->employee_code = $employee->employee_id;
            $log->department = $employee->department?->name ?? 'No Department';
            $log->formatted_location_type = $attRecord ? $attRecord->formatted_location_type : '—';
            $log->location_type = $attRecord ? $attRecord->location_type : '';
            $log->check_in = $attRecord ? $attRecord->check_in : null;
            $log->check_out = $attRecord ? $attRecord->check_out : null;
            $log->breaks = $attRecord ? $attRecord->breaks : collect();
            $log->total_break_hours = $attRecord ? $attRecord->total_break_hours : 0;
            $log->formatted_break_hours = $attRecord ? $attRecord->formatted_break_hours : '—';
            $log->formatted_work_hours = $attRecord ? $attRecord->formatted_work_hours : '—';
            $log->status = $resolved['status'];
            $log->holiday_name = $resolved['holiday_name'];
            $log->leave_type_name = $resolved['leave_type_name'];
            $log->check_in_latitude = $attRecord ? $attRecord->check_in_latitude : null;
            $log->check_in_longitude = $attRecord ? $attRecord->check_in_longitude : null;
            $log->check_out_latitude = $attRecord ? $attRecord->check_out_latitude : null;
            $log->check_out_longitude = $attRecord ? $attRecord->check_out_longitude : null;
            $log->check_in_selfie_path = $attRecord ? $attRecord->check_in_selfie_path : null;
            $log->check_out_selfie_path = $attRecord ? $attRecord->check_out_selfie_path : null;
            $log->locationLogs = $attRecord ? $attRecord->locationLogs : collect();

            return $log;
        });

        return response()->json([
            'date' => $carbonDate->format('M d, Y'),
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

                $status = $log->status;
                $isAbsentOrLeave = in_array($status, ['absent', 'on_leave', 'week_off', 'holiday']);

                if ($status === 'present') {
                    $statusBadge = '<span class="badge bg-soft-success text-success">Present</span>';
                } elseif ($status === 'wfh') {
                    $statusBadge = '<span class="badge bg-soft-info text-info">WFH</span>';
                } elseif ($status === 'late') {
                    $statusBadge = '<span class="badge bg-soft-warning text-warning">Late</span>';
                } elseif ($status === 'half_day') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Half Day</span>';
                } elseif ($status === 'on_leave') {
                    $statusBadge = '<span class="badge bg-soft-primary text-primary">On Leave: ' . ($log->leave_type_name ?? 'Leave') . '</span>';
                } elseif ($status === 'absent') {
                    $statusBadge = '<span class="badge bg-soft-danger text-danger">Absent</span>';
                } elseif ($status === 'week_off') {
                    $statusBadge = '<span class="badge bg-soft-secondary text-secondary">Week Off</span>';
                } elseif ($status === 'holiday') {
                    $statusBadge = '<span class="badge bg-soft-indigo text-indigo" style="background-color: rgba(79, 70, 229, 0.1); color: #4f46e5 !important;">Holiday: ' . ($log->holiday_name ?? '') . '</span>';
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
                    'employee_name' => $log->employee_name,
                    'employee_code' => $log->employee_code,
                    'department' => $log->department,
                    'location_type' => $log->formatted_location_type,
                    'check_in' => $checkInDisplay,
                    'check_out' => $checkOutDisplay,
                    'breaks' => $breaksHtml,
                    'work_hours' => $workHoursDisplay,
                    'status' => $statusBadge,
                    'status_raw' => $status,
                    'has_overtime' => in_array($log->employee->id, $approvedOvertimeEmployeeIds),
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

    /**
     * Resolves the attendance status for a given employee on a specific date.
     */
    private function resolveStatusForDate(
        $employee,
        \Carbon\Carbon $date,
        $attendanceRecord,
        $holidays,
        $leaves
    ) {
        // 1. If a real punch record exists, use its status
        if ($attendanceRecord) {
            $status = $attendanceRecord->status ?: 'present';
            // WFH can be determined by location_type too
            if (strtolower($attendanceRecord->location_type) === 'wfh' && $status === 'present') {
                $status = 'wfh';
            }
            return [
                'status' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
                'record' => $attendanceRecord,
                'holiday_name' => null,
                'leave_type_name' => null
            ];
        }

        $dateStr = $date->format('Y-m-d');

        // 2. Check if it is a holiday
        $holiday = $holidays->first(function($h) use ($employee, $dateStr) {
            $hDate = $h->holiday_date instanceof \Carbon\Carbon ? $h->holiday_date->format('Y-m-d') : \Carbon\Carbon::parse($h->holiday_date)->format('Y-m-d');
            if ($hDate !== $dateStr) {
                return false;
            }
            // Check scope
            if (is_null($h->company_id) && is_null($h->business_unit_id) && is_null($h->branch_id)) {
                return true;
            }
            if ($h->company_id == $employee->company_id) {
                if (is_null($h->business_unit_id) && is_null($h->branch_id)) {
                    return true;
                }
                if ($h->business_unit_id == $employee->business_unit_id) {
                    if (is_null($h->branch_id) || $h->branch_id == $employee->branch_id) {
                        return true;
                    }
                }
            }
            return false;
        });

        if ($holiday) {
            return [
                'status' => 'holiday',
                'label' => 'Holiday: ' . $holiday->name,
                'record' => null,
                'holiday_name' => $holiday->name,
                'leave_type_name' => null
            ];
        }

        // 3. Check if it is a week off
        $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
        $isWeekOff = false;
        if (isset($employee->weekly_pattern) && is_array($employee->weekly_pattern)) {
            if (isset($employee->weekly_pattern[$dayOfWeek]) && $employee->weekly_pattern[$dayOfWeek] === 'off') {
                $isWeekOff = true;
            } elseif ($dayOfWeek === 0 && (!isset($employee->weekly_pattern[$dayOfWeek]) || $employee->weekly_pattern[$dayOfWeek] === 'off')) {
                // Sunday is off by default unless overridden to a shift in weekly pattern
                $isWeekOff = true;
            }
        } else {
            // No weekly pattern, Sunday is default week off
            if ($dayOfWeek === 0) {
                $isWeekOff = true;
            }
        }

        if ($isWeekOff) {
            return [
                'status' => 'week_off',
                'label' => 'Week Off',
                'record' => null,
                'holiday_name' => null,
                'leave_type_name' => null
            ];
        }

        // 4. Check if it is an approved leave
        $leave = $leaves->first(function($l) use ($employee, $dateStr) {
            if ($l->employee_id != $employee->id) {
                return false;
            }
            $start = $l->start_date instanceof \Carbon\Carbon ? $l->start_date->format('Y-m-d') : \Carbon\Carbon::parse($l->start_date)->format('Y-m-d');
            $end = $l->end_date instanceof \Carbon\Carbon ? $l->end_date->format('Y-m-d') : \Carbon\Carbon::parse($l->end_date)->format('Y-m-d');
            return $dateStr >= $start && $dateStr <= $end;
        });

        if ($leave) {
            $typeName = $leave->leaveType ? $leave->leaveType->name : 'Leave';
            return [
                'status' => 'on_leave',
                'label' => 'Leave',
                'record' => null,
                'holiday_name' => null,
                'leave_type_name' => $typeName
            ];
        }

        // 5. Past days are Absent, future days are Scheduled/Upcoming
        if ($date->isFuture() && !$date->isToday()) {
            return [
                'status' => 'scheduled',
                'label' => 'Scheduled',
                'record' => null,
                'holiday_name' => null,
                'leave_type_name' => null
            ];
        }

        return [
            'status' => 'absent',
            'label' => 'Absent',
            'record' => null,
            'holiday_name' => null,
            'leave_type_name' => null
        ];
    }
}
