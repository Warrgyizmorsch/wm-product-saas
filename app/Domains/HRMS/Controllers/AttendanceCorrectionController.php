<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceCorrectionController extends Controller
{
    /**
     * Formats a LengthAwarePaginator into a clean, concise response structure.
     */
    private function formatPaginatedData(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, callable $transformItem): array
    {
        return [
            'items' => collect($paginator->items())->map($transformItem)->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * Transforms an AttendanceCorrection model into a clean array structure.
     */
    private function transformCorrection(AttendanceCorrection $ac): array
    {
        $data = [
            'id'                  => $ac->id,
            'date'                => $ac->date ? $ac->date->format('Y-m-d') : null,
            'requested_check_in'  => $ac->requested_check_in ? $ac->requested_check_in->format('H:i:s') : null,
            'requested_check_out' => $ac->requested_check_out ? $ac->requested_check_out->format('H:i:s') : null,
            'reason'              => $ac->reason,
            'status'              => $ac->status,
            'rejected_reason'     => $ac->rejected_reason,
            'created_at'          => $ac->created_at?->toDateTimeString(),
        ];

        if ($ac->relationLoaded('employee') && $ac->employee) {
            $data['employee'] = [
                'id'            => $ac->employee->id,
                'employee_code' => $ac->employee->employee_id,
                'full_name'     => $ac->employee->full_name,
                'photo'         => $ac->employee->photo,
            ];
        }

        if ($ac->relationLoaded('attendance') && $ac->attendance) {
            $att = $ac->attendance;
            $data['attendance'] = [
                'id'         => $att->id,
                'check_in'   => $att->check_in ? $att->check_in->toDateTimeString() : null,
                'check_out'  => $att->check_out ? $att->check_out->toDateTimeString() : null,
                'status'     => $att->status,
                'work_hours' => floatval($att->total_work_hours ?? 0),
            ];
        }

        return $data;
    }

    /**
     * GET /api/hrms/attendance-corrections
     * List paginated attendance correction requests with filtering.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user?->id)->first();
        $isAdmin = $user && (method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['admin', 'hr', 'super-admin']) : true);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $query = AttendanceCorrection::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,photo',
                'attendance:id,check_in,check_out,status,total_work_hours',
            ]);

        if ($request->filled('employee_id')) {
            if ($isAdmin || ($employee && (int)$request->employee_id === (int)$employee->id)) {
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->where('employee_id', $employee?->id ?? 0);
            }
        } elseif (!$isAdmin && $employee) {
            $query->where('employee_id', $employee->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min($request->integer('per_page', 10), 100);
        $corrections = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = $this->formatPaginatedData($corrections, fn($ac) => $this->transformCorrection($ac));

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Attendance correction requests retrieved successfully.',
                'data'    => $formatted,
            ]);
        }

        return $corrections;
    }

    /**
     * GET /api/hrms/attendance-corrections/{id}
     * Get single attendance correction request details.
     */
    public function show(mixed $id)
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user?->id)->first();
        $isAdmin = $user && (method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['admin', 'hr', 'super-admin']) : true);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $correction = AttendanceCorrection::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,office_email,personal_email,photo',
                'attendance:id,date,check_in,check_out,status,total_work_hours,total_break_hours',
            ])
            ->find($id);

        if (!$correction) {
            return response()->json([
                'success' => false,
                'message' => "Attendance correction request with ID '{$id}' not found."
            ], 404);
        }

        if (!$isAdmin && $employee && (int)$correction->employee_id !== (int)$employee->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this attendance correction request.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance correction request details loaded successfully.',
            'data'    => $this->transformCorrection($correction)
        ]);
    }

    /**
     * Store a newly created attendance correction request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'attendance_id'       => 'nullable|integer',
            'date'                => 'required|date',
            'requested_check_in'  => 'required|string', // Format: HH:MM
            'requested_check_out' => 'required|string', // Format: HH:MM
            'reason'              => 'required|string|max:500',
        ]);

        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your user session is not linked to an Employee profile.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Your user session is not linked to an Employee profile.');
        }

        // Prevent duplicate requests
        $existing = AttendanceCorrection::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending correction request for this date.'
                ], 422);
            }
            return redirect()->back()->with('error', 'You already have a pending correction request for this date.');
        }

        // Combine date and times
        $dateStr = $validated['date'];
        $checkInTime = $validated['requested_check_in'];
        $checkOutTime = $validated['requested_check_out'];

        try {
            $requestedCheckIn = Carbon::parse($dateStr . ' ' . $checkInTime);
            $requestedCheckOut = Carbon::parse($dateStr . ' ' . $checkOutTime);

            if ($requestedCheckOut->lessThanOrEqualTo($requestedCheckIn)) {
                // If check-out is on the next day, add 1 day
                $requestedCheckOut->addDay();
            }
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid check-in or check-out time format.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Invalid check-in or check-out time format.');
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Resolve attendance_id from date and employee if not explicitly provided
        $resolvedAttendanceId = ($validated['attendance_id'] ?? null) ?: Attendance::where('employee_id', $employee->id)->whereDate('date', $dateStr)->value('id');

        // Create the request
        $correction = AttendanceCorrection::create([
            'tenant_id'           => $tenantId,
            'employee_id'         => $employee->id,
            'attendance_id'       => $resolvedAttendanceId,
            'date'                => $dateStr,
            'requested_check_in'  => $requestedCheckIn,
            'requested_check_out' => $requestedCheckOut,
            'reason'              => $validated['reason'],
            'status'              => 'pending',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Correction request submitted successfully.',
                'data' => $correction
            ]);
        }

        return redirect()->back()->with('success', 'Correction request submitted successfully.');
    }

    /**
     * Approve the correction request.
     */
    public function approve(Request $request, AttendanceCorrection $correction)
    {
        if ($correction->status !== 'pending') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request is no longer pending.'
                ], 422);
            }
            return redirect()->back()->with('error', 'This request is no longer pending.');
        }

        $validated = $request->validate([
            'approved_check_in'  => 'nullable|string',
            'approved_check_out' => 'nullable|string',
        ]);

        $dateStr = $correction->date->format('Y-m-d');
        $checkInTime = $validated['approved_check_in'] ?? null;
        $checkOutTime = $validated['approved_check_out'] ?? null;

        DB::beginTransaction();

        try {
            // 1. Re-calculate work hours
            $attendance = $correction->attendance;
            if (!$attendance) {
                // Search for any existing attendance for this date & employee
                $attendance = Attendance::where('employee_id', $correction->employee_id)
                    ->whereDate('date', $correction->date)
                    ->first();
            }

            // Calculate breaks
            $totalBreakHours = 0.00;
            if ($attendance) {
                $attendance->load('breaks');
                $totalBreakMinutes = $attendance->breaks->sum('duration_minutes');
                $totalBreakHours = round($totalBreakMinutes / 60, 2);
            }

            if ($checkInTime) {
                $checkIn = Carbon::parse($dateStr . ' ' . $checkInTime);
            } else {
                $checkIn = Carbon::parse($correction->requested_check_in);
            }

            if ($checkOutTime) {
                $checkOut = Carbon::parse($dateStr . ' ' . $checkOutTime);
            } else {
                $checkOut = Carbon::parse($correction->requested_check_out);
            }

            if ($checkOut->lessThanOrEqualTo($checkIn)) {
                $checkOut->addDay();
            }

            $totalDiffHours = $checkIn->diffInMinutes($checkOut) / 60;
            $totalWorkHours = max(0, round($totalDiffHours - $totalBreakHours, 2));

            // 2. Create or Update the Attendance Record
            if ($attendance) {
                $attendance->update([
                    'check_in'          => $checkIn,
                    'check_out'         => $checkOut,
                    'total_work_hours'  => $totalWorkHours,
                    'status'            => 'present', // Regularized attendance is marked present
                ]);
            } else {
                $attendance = Attendance::create([
                    'tenant_id'         => $correction->tenant_id,
                    'employee_id'       => $correction->employee_id,
                    'date'              => $correction->date,
                    'check_in'          => $checkIn,
                    'check_out'         => $checkOut,
                    'total_work_hours'  => $totalWorkHours,
                    'total_break_hours' => $totalBreakHours,
                    'location_type'     => 'office',
                    'status'            => 'present',
                ]);
            }

            // 3. Excuse any matching attendance penalties
            EmployeePenalty::where('employee_id', $correction->employee_id)
                ->whereDate('date', $correction->date)
                ->update([
                    'status'         => 'excused',
                    'penalty_amount' => 0.00,
                    'remarks'        => DB::raw("CONCAT(remarks, ' - Excused via Attendance Correction Request')")
                ]);

            // 4. Update the Correction Request with final approved times
            $correction->update([
                'requested_check_in'  => $checkIn,
                'requested_check_out' => $checkOut,
                'status'              => 'approved',
                'approved_by'         => auth()->id(),
                'attendance_id'       => $attendance->id
            ]);

            // 5. Queue Retro LOP Reversal if the corrected date falls in a locked/paid payroll month
            $payrollMonth = $correction->date->format('Y-m');
            $lockedRunExists = \App\Domains\HRMS\Models\PayrollRun::where('company_id', $correction->employee->company_id)
                ->where('payroll_month', $payrollMonth)
                ->whereIn('status', ['locked', 'paid'])
                ->exists();

            if ($lockedRunExists) {
                // Fetch employee's monthly basic salary to compute daily wage LOP reversal
                $employee = $correction->employee;
                $salary = $employee->current_salary; // Annual CTC
                
                // Get active structure mapping
                $baseStructure = \App\Domains\HRMS\Models\SalaryStructure::where('pay_group_id', $employee->pay_group_id)
                    ->where('min_ctc', '<=', $salary)
                    ->where('max_ctc', '>=', $salary)
                    ->where('status', true)
                    ->first();
                if (!$baseStructure) {
                    $baseStructure = $employee->salaryStructure;
                }

                $monthlyBasic = ($salary * 0.50) / 12; // Fallback
                if ($baseStructure && $baseStructure->items) {
                    $basicItem = $baseStructure->items->filter(fn($i) => $i->component->code === 'BASIC')->first();
                    if ($basicItem) {
                        if ($basicItem->calculation_type === 'percentage_of_ctc') {
                            $monthlyBasic = (($salary * $basicItem->value) / 100) / 12;
                        } elseif ($basicItem->calculation_type === 'fixed') {
                            $monthlyBasic = $basicItem->value;
                        }
                    }
                }
                
                // Read proration rules
                $rules = $employee->payGroup ? ($employee->payGroup->payroll_rules ?? []) : [];
                $prorationRule = $rules['proration_rule'] ?? 'calendar_days';
                
                $divisor = $correction->date->daysInMonth;
                if ($prorationRule === 'fixed_30_days') {
                    $divisor = 30;
                }

                $dailyBasicRate = $monthlyBasic / $divisor;

                // Create the retro adjustment record
                \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::create([
                    'tenant_id'            => $employee->tenant_id,
                    'employee_id'          => $correction->employee_id,
                    'target_payroll_month' => $payrollMonth,
                    'reversal_days'        => 1,
                    'amount_reversal'      => round($dailyBasicRate, 2),
                    'status'               => 'pending',
                ]);
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Correction request approved successfully.',
                    'data' => $correction
                ]);
            }

            return redirect()->back()->with('success', 'Correction request approved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve attendance correction', [
                'id' => $correction->id,
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred during approval: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to approve request: ' . $e->getMessage());
        }
    }

    /**
     * Reject the correction request.
     */
    public function reject(Request $request, AttendanceCorrection $correction)
    {
        if ($correction->status !== 'pending') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request is no longer pending.'
                ], 422);
            }
            return redirect()->back()->with('error', 'This request is no longer pending.');
        }

        $validated = $request->validate([
            'rejected_reason' => 'required|string|max:500',
        ]);

        $correction->update([
            'status'          => 'rejected',
            'approved_by'     => auth()->id(),
            'rejected_reason' => $validated['rejected_reason']
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Correction request rejected successfully.',
                'data' => $correction
            ]);
        }

        return redirect()->back()->with('success', 'Correction request rejected successfully.');
    }
}
