<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\PayrollCalculationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\OvertimeRequest;

class PayrollRunApiController extends Controller
{
    public function __construct(
        private readonly PayrollCalculationService $payrollCalculationService
    ) {}

    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();
            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }
        return null;
    }

    /**
     * GET /api/hrms/payroll
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $runs = PayrollRun::orderBy('payroll_month', 'desc')->get();
        
        $selectedRunId = $request->get('run_id');
        $selectedRun = $selectedRunId ? PayrollRun::find($selectedRunId) : $runs->first();

        $payGroups = \App\Domains\HRMS\Models\PayGroup::where('status', true)->get();

        $registerData = [];
        $pendingPriorHolds = [];
        if ($selectedRun) {
            $excludeEmployeeIds = [];
            if (empty($selectedRun->employee_ids)) {
                $otherRuns = PayrollRun::where('payroll_month', $selectedRun->payroll_month)
                    ->where('id', '!=', $selectedRun->id)
                    ->get();

                foreach ($otherRuns as $or) {
                    if ($or->employee_ids && count($or->employee_ids) > 0) {
                        $excludeEmployeeIds = array_merge($excludeEmployeeIds, $or->employee_ids);
                    } elseif ($or->pay_group_id) {
                        $pgEmpIds = Employee::where('pay_group_id', $or->pay_group_id)->pluck('id')->toArray();
                        $excludeEmployeeIds = array_merge($excludeEmployeeIds, $pgEmpIds);
                    } else {
                        $allEmpIds = Employee::pluck('id')->toArray();
                        $excludeEmployeeIds = array_merge($excludeEmployeeIds, $allEmpIds);
                    }
                }
                $excludeEmployeeIds = array_unique($excludeEmployeeIds);
            }

            if ($selectedRun->employee_ids && count($selectedRun->employee_ids) > 0) {
                $employees = Employee::whereIn('id', $selectedRun->employee_ids)
                    ->whereNotIn('id', $excludeEmployeeIds)
                    ->where('status', true)
                    ->whereNotNull('salary_structure_id')
                    ->get();
            } else {
                $employeesQuery = Employee::where('status', true)
                    ->whereNotNull('pay_group_id')
                    ->whereNotNull('salary_structure_id')
                    ->whereNotIn('id', $excludeEmployeeIds);

                if ($selectedRun->pay_group_id) {
                    $employeesQuery->where('pay_group_id', $selectedRun->pay_group_id);
                } else {
                    $otherProcessedPayGroupIds = PayrollRun::where('payroll_month', $selectedRun->payroll_month)
                        ->where('id', '!=', $selectedRun->id)
                        ->whereNotNull('pay_group_id')
                        ->pluck('pay_group_id')
                        ->toArray();

                    if (!empty($otherProcessedPayGroupIds)) {
                        $employeesQuery->whereNotIn('pay_group_id', $otherProcessedPayGroupIds);
                    }
                }

                $employees = $employeesQuery->get();
            }

            foreach ($employees as $employee) {
                $calc = $this->payrollCalculationService->calculateSalary($employee, $selectedRun->payroll_month);
                
                $holdRecord = PayrollHold::where('employee_id', $employee->id)
                    ->where('payroll_month', $selectedRun->payroll_month)
                    ->first();
                
                $isHeld = $holdRecord && $holdRecord->status === 'on_hold';
                $holdStatus = $holdRecord ? $holdRecord->status : null;

                $registerData[] = [
                    'employee'    => $employee,
                    'is_held'     => $isHeld,
                    'hold_status' => $holdStatus,
                    'calc'        => $calc['summary'] ?? [
                        'employee_name'        => $employee->full_name,
                        'total_earnings'       => 0.00,
                        'total_deductions'     => 0.00,
                        'lop_days'             => 0,
                        'attendance_penalties'    => 0.00,
                        'attendance_penalty_days' => 0.00,
                        'net_payout'              => 0.00,
                    ],
                    'items'       => $calc['items'] ?? [],
                ];
            }

            $priorHolds = PayrollHold::where('status', 'on_hold')
                ->where('payroll_month', '<', $selectedRun->payroll_month)
                ->get();

            foreach ($priorHolds as $hold) {
                if ($hold->employee) {
                    $calc = $this->payrollCalculationService->calculateSalary($hold->employee, $hold->payroll_month);
                    $pendingPriorHolds[] = [
                        'hold'       => $hold,
                        'employee'   => $hold->employee,
                        'net_payout' => $calc['summary']['net_payout'] ?? 0.00
                    ];
                }
            }
        }

        $pendingIssues = $selectedRun ? $this->getPendingIssues($selectedRun) : ['leaves' => 0, 'corrections' => 0, 'overtime' => 0, 'total' => 0];
        $salaryComponents = \App\Domains\HRMS\Models\SalaryComponent::where('status', true)
            ->where('is_adhoc', true)
            ->get();
        $allEmployees = Employee::where('status', true)->orderBy('full_name')->get();
        $departments = \App\Domains\HRMS\Models\Department::orderBy('name')->get();

        $registerCollection = collect($registerData);
        
        $search = $request->get('search');
        if ($search) {
            $search = strtolower(trim($search));
            $registerCollection = $registerCollection->filter(function($row) use ($search) {
                $name = strtolower($row['employee']->full_name);
                $empId = strtolower($row['employee']->employee_id);
                return str_contains($name, $search) || str_contains($empId, $search);
            });
        }

        $status = $request->get('status');
        if ($status) {
            $registerCollection = $registerCollection->filter(function($row) use ($status, $selectedRun) {
                if ($status === 'held') {
                    return $selectedRun->status === 'paid' ? ($row['hold_status'] === 'on_hold') : $row['is_held'];
                } elseif ($status === 'approved') {
                    return $selectedRun->status === 'paid' ? ($row['hold_status'] !== 'on_hold') : !$row['is_held'];
                }
                return true;
            });
        }

        $deptId = $request->get('department_id');
        if ($deptId) {
            $registerCollection = $registerCollection->filter(function($row) use ($deptId) {
                return $row['employee']->department_id == $deptId;
            });
        }

        $sort = $request->get('sort', 'name_asc');
        if ($sort === 'name_asc') {
            $registerCollection = $registerCollection->sortBy(function($row) {
                return strtolower($row['employee']->full_name);
            });
        } elseif ($sort === 'name_desc') {
            $registerCollection = $registerCollection->sortByDesc(function($row) {
                return strtolower($row['employee']->full_name);
            });
        } elseif ($sort === 'net_desc') {
            $registerCollection = $registerCollection->sortByDesc(function($row) {
                return (float)($row['calc']['net_payout'] ?? 0);
            });
        } elseif ($sort === 'net_asc') {
            $registerCollection = $registerCollection->sortBy(function($row) {
                return (float)($row['calc']['net_payout'] ?? 0);
            });
        } elseif ($sort === 'lop_desc') {
            $registerCollection = $registerCollection->sortByDesc(function($row) {
                return (float)($row['calc']['lop_days'] ?? 0);
            });
        }

        $registerData = $registerCollection->values()->all();

        return $this->sendSuccess([
            'runs'               => $runs,
            'selected_run'       => $selectedRun,
            'register_data'      => $registerData,
            'pay_groups'         => $payGroups,
            'pending_prior_holds'=> $pendingPriorHolds,
            'pending_issues'     => $pendingIssues,
            'salary_components'  => $salaryComponents,
            'all_employees'      => $allEmployees,
            'departments'        => $departments,
            'filters'            => [
                'search'        => $request->get('search'),
                'sort'          => $request->get('sort', 'name_asc'),
                'status'        => $request->get('status'),
                'department_id' => $request->get('department_id'),
            ],
        ], 'Payroll overview and registers loaded successfully');
    }

    /**
     * POST /api/hrms/payroll/store
     */
    public function storeRun(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'payroll_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'pay_group_id'  => 'nullable|exists:pay_groups,id',
            'employee_ids'  => 'nullable|array',
            'employee_ids.*'=> 'exists:employees,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        if (!empty($validated['pay_group_id'])) {
            $generalExists = PayrollRun::where('payroll_month', $validated['payroll_month'])
                ->whereNull('pay_group_id')
                ->where(function($q) {
                    $q->whereNull('employee_ids')->orWhere('employee_ids', '[]');
                })
                ->exists();
            if ($generalExists) {
                return $this->sendError("A general payroll run for all pay groups already exists for {$validated['payroll_month']}.", 400);
            }
        }

        if (empty($validated['employee_ids'])) {
            $existsQuery = PayrollRun::where('payroll_month', $validated['payroll_month']);
            if (!empty($validated['pay_group_id'])) {
                $existsQuery->where('pay_group_id', $validated['pay_group_id']);
            } else {
                $existsQuery->whereNull('pay_group_id')->where(function($q) {
                    $q->whereNull('employee_ids')->orWhere('employee_ids', '[]');
                });
            }

            if ($existsQuery->exists()) {
                $label = !empty($validated['pay_group_id']) ? 'this pay group' : 'all pay groups';
                return $this->sendError("A payroll run already exists for {$validated['payroll_month']} and {$label}.", 400);
            }
        } else {
            // Check if any of the selected employees are already processed in another run for this month
            $otherRuns = PayrollRun::where('payroll_month', $validated['payroll_month'])->get();
            $alreadyProcessed = [];
            foreach ($otherRuns as $or) {
                if ($or->employee_ids && count($or->employee_ids) > 0) {
                    $alreadyProcessed = array_merge($alreadyProcessed, $or->employee_ids);
                } elseif ($or->pay_group_id) {
                    $pgEmpIds = Employee::where('pay_group_id', $or->pay_group_id)->pluck('id')->toArray();
                    $alreadyProcessed = array_merge($alreadyProcessed, $pgEmpIds);
                } else {
                    $allEmpIds = Employee::pluck('id')->toArray();
                    $alreadyProcessed = array_merge($alreadyProcessed, $allEmpIds);
                }
            }
            $alreadyProcessed = array_unique($alreadyProcessed);
            $overlap = array_intersect($validated['employee_ids'], $alreadyProcessed);
            if (!empty($overlap)) {
                $names = Employee::whereIn('id', $overlap)->pluck('full_name')->toArray();
                return $this->sendError("Cannot initiate payroll: The following employees have already been processed in another run for this month: " . implode(', ', $names), 400);
            }
        }

        $run = PayrollRun::create([
            'company_id'    => auth()->user()->company_id ?? 1,
            'pay_group_id'  => $validated['pay_group_id'] ?? null,
            'employee_ids'  => $validated['employee_ids'] ?? null,
            'payroll_month' => $validated['payroll_month'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'status'        => 'draft',
            'processed_by'  => auth()->id(),
        ]);

        return $this->sendSuccess($run, 'Payroll run initiated successfully.');
    }

    /**
     * POST /api/hrms/payroll/{run}/lock
     */
    public function lockRun(PayrollRun $run): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $pending = $this->getPendingIssues($run);
        if ($pending['total'] > 0) {
            return $this->sendError(
                "Cannot lock payroll: There are {$pending['total']} pending requests (Leaves: {$pending['leaves']}, Corrections: {$pending['corrections']}, Overtime: {$pending['overtime']}). Please resolve them first.",
                400,
                $pending
            );
        }

        $run->update(['status' => 'locked']);
        return $this->sendSuccess($run, 'Payroll run locked successfully.');
    }

    /**
     * POST /api/hrms/payroll/{run}/resolve
     */
    public function resolvePending(Request $request, PayrollRun $run): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'resolution_action' => 'required|string|in:approve_all,reject_all',
        ]);

        $resolution = $validated['resolution_action'];
        $startDate = $run->start_date;
        $endDate = $run->end_date;

        $leavesQuery = LeaveRequest::where('status', 'pending')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        $correctionsQuery = AttendanceCorrection::where('status', 'pending')
            ->whereBetween('date', [$startDate, $endDate]);

        $overtimesQuery = OvertimeRequest::where('status', 'pending')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($run->employee_ids && count($run->employee_ids) > 0) {
            $leavesQuery->whereIn('employee_id', $run->employee_ids);
            $correctionsQuery->whereIn('employee_id', $run->employee_ids);
            $overtimesQuery->whereIn('employee_id', $run->employee_ids);
        }

        DB::beginTransaction();
        try {
            if ($resolution === 'approve_all') {
                $leaves = $leavesQuery->get();
                
                $leaveRepo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, ['action' => 'approved'], $request);
                }

                $corrections = $correctionsQuery->get();

                $correctionController = app(\App\Domains\HRMS\Controllers\AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $correctionController->approve($request, $correction);
                }

                $overtimes = $overtimesQuery->get();

                $otRepo = app(\App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface::class);
                foreach ($overtimes as $ot) {
                    $otRepo->updateStatus($ot, [
                        'action' => 'approved',
                        'approved_duration_hours' => $ot->duration_hours
                    ], $request);
                }

                $message = "All pending requests approved and payroll calculations updated.";
            } else {
                $leaves = $leavesQuery->get();
                
                $leaveRepo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, [
                        'action' => 'rejected',
                        'rejection_reason' => 'Auto-rejected during payroll run execution.'
                    ], $request);
                }

                $corrections = $correctionsQuery->get();

                $correctionController = app(\App\Domains\HRMS\Controllers\AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $fakeRequest = clone $request;
                    $fakeRequest->merge(['rejected_reason' => 'Auto-rejected during payroll run execution.']);
                    $correctionController->reject($fakeRequest, $correction);
                }

                $overtimes = $overtimesQuery->get();

                $otRepo = app(\App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface::class);
                foreach ($overtimes as $ot) {
                    $otRepo->updateStatus($ot, [
                        'action' => 'rejected',
                        'rejection_reason' => 'Auto-rejected during payroll run execution.'
                    ], $request);
                }

                $message = "All pending requests rejected. Unresolved days calculated as LOP/zeros.";
            }

            DB::commit();
            return $this->sendSuccess($run->fresh(), $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to auto-resolve payroll issues", [
                'run_id' => $run->id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError("Failed to resolve pending requests: " . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/payroll/{run}/release
     */
    public function releasePayouts(PayrollRun $run): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $run->update(['status' => 'paid']);
        
        DB::table('payroll_retroactive_adjustments')
            ->where('target_payroll_month', $run->payroll_month)
            ->where('status', 'pending')
            ->update(['status' => 'processed']);

        DB::table('employee_adhoc_components')
            ->where('payroll_month', $run->payroll_month)
            ->where('status', 'pending')
            ->update(['status' => 'processed']);

        $excludeEmployeeIds = [];
        if (empty($run->employee_ids)) {
            $otherRuns = PayrollRun::where('payroll_month', $run->payroll_month)
                ->where('id', '!=', $run->id)
                ->get();

            foreach ($otherRuns as $or) {
                if ($or->employee_ids && count($or->employee_ids) > 0) {
                    $excludeEmployeeIds = array_merge($excludeEmployeeIds, $or->employee_ids);
                } elseif ($or->pay_group_id) {
                    $pgEmpIds = Employee::where('pay_group_id', $or->pay_group_id)->pluck('id')->toArray();
                    $excludeEmployeeIds = array_merge($excludeEmployeeIds, $pgEmpIds);
                } else {
                    $allEmpIds = Employee::pluck('id')->toArray();
                    $excludeEmployeeIds = array_merge($excludeEmployeeIds, $allEmpIds);
                }
            }
            $excludeEmployeeIds = array_unique($excludeEmployeeIds);
        }

        if ($run->employee_ids && count($run->employee_ids) > 0) {
            $employees = Employee::whereIn('id', $run->employee_ids)
                ->whereNotIn('id', $excludeEmployeeIds)
                ->where('status', true)
                ->whereNotNull('salary_structure_id')
                ->get();
        } else {
            $employeesQuery = Employee::where('status', true)
                ->whereNotNull('pay_group_id')
                ->whereNotNull('salary_structure_id')
                ->whereNotIn('id', $excludeEmployeeIds);

            if ($run->pay_group_id) {
                $employeesQuery->where('pay_group_id', $run->pay_group_id);
            } else {
                $otherProcessedPayGroupIds = PayrollRun::where('payroll_month', $run->payroll_month)
                    ->where('id', '!=', $run->id)
                    ->whereNotNull('pay_group_id')
                    ->pluck('pay_group_id')
                    ->toArray();
                if (!empty($otherProcessedPayGroupIds)) {
                    $employeesQuery->whereNotIn('pay_group_id', $otherProcessedPayGroupIds);
                }
            }
            $employees = $employeesQuery->get();
        }

        $allOtIds = [];
        $allEncashIds = [];
        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            if (!empty($calc['summary']['processed_ot_ids'])) {
                $allOtIds = array_merge($allOtIds, $calc['summary']['processed_ot_ids']);
            }
            if (!empty($calc['summary']['processed_encash_ids'])) {
                $allEncashIds = array_merge($allEncashIds, $calc['summary']['processed_encash_ids']);
            }
        }

        if (!empty($allOtIds)) {
            DB::table('overtime_requests')
                ->whereIn('id', $allOtIds)
                ->update(['status' => 'processed']);
        }

        if (!empty($allEncashIds)) {
            DB::table('leave_encashments')
                ->whereIn('id', $allEncashIds)
                ->update(['status' => 'processed']);
        }

        // Integrate with Accounting (Consolidated Single Journal Entry)
        $tenantId = $run->tenant_id ?? (tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id());
        $totalEarnings = 0.00;
        $totalDeductions = 0.00;
        $totalNetPayout = 0.00;

        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            $summary = $calc['summary'] ?? [];
            $totalEarnings += floatval($summary['total_earnings'] ?? 0.00);
            $totalDeductions += floatval($summary['total_deductions'] ?? 0.00);
            $totalNetPayout += floatval($summary['net_payout'] ?? 0.00);
        }

        if ($totalNetPayout > 0 || $totalEarnings > 0) {
            $expenseAccount = $this->getOrCreateAccount($tenantId, '5100', 'Salary & Wages - Staff', 'expense', 'debit', 'indirect_expense');
            $statutoryAccount = $this->getOrCreateAccount($tenantId, '2080', 'Statutory Dues Payable', 'liability', 'credit', 'current_liability');
            $bankAccount = $this->getOrCreateAccount($tenantId, '1020', 'Bank Account', 'asset', 'debit', 'current_asset');

            $lines = [];

            // 1. DR Salary Expense
            if ($totalEarnings > 0) {
                $lines[] = [
                    'chart_of_account_id' => $expenseAccount->id,
                    'debit' => $totalEarnings,
                    'credit' => 0.00,
                    'description' => "Salary Expense for payroll run: " . $run->payroll_month
                ];
            }

            // 2. CR Statutory Payables
            if ($totalDeductions > 0) {
                $lines[] = [
                    'chart_of_account_id' => $statutoryAccount->id,
                    'debit' => 0.00,
                    'credit' => $totalDeductions,
                    'description' => "Employee deductions for payroll run: " . $run->payroll_month
                ];
            }

            // 3. CR Bank Account (Cash Out)
            if ($totalNetPayout > 0) {
                $lines[] = [
                    'chart_of_account_id' => $bankAccount->id,
                    'debit' => 0.00,
                    'credit' => $totalNetPayout,
                    'description' => "Bank Transfer payment for payroll run: " . $run->payroll_month
                ];
            }

            try {
                $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                $journalService->post($lines, [
                    'tenant_id' => $tenantId,
                    'journal_date' => now(),
                    'source' => 'payroll',
                    'reference_type' => 'PayrollRun',
                    'reference_id' => $run->id,
                    'memo' => "Payroll Payout for month: " . $run->payroll_month,
                    'posted_by' => auth()->id(),
                ]);
            } catch (\Exception $e) {
                Log::error("Payroll Payout Journal Posting Failed: " . $e->getMessage());
            }
        }

        return $this->sendSuccess($run->fresh(), 'Payroll payouts released successfully.');
    }

    /**
     * POST /api/hrms/payroll/hold/toggle
     */
    public function toggleHold(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'payroll_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'target_month'  => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $employee = Employee::find($validated['employee_id']);
        $month = $validated['payroll_month'];

        $hold = PayrollHold::where('employee_id', $employee->id)
            ->where('payroll_month', $month)
            ->first();

        if ($hold) {
            if ($hold->status === 'on_hold') {
                $hold->update(['status' => 'released']);
                $msg = 'Payout released for ' . $employee->full_name;

                $run = PayrollRun::where('payroll_month', $month)->first();
                if ($run && in_array($run->status, ['locked', 'paid'])) {
                    $calc = $this->payrollCalculationService->calculateSalary($employee, $month);
                    $netPayout = $calc['summary']['net_payout'] ?? 0.00;

                    if ($netPayout > 0) {
                        $targetMonthStr = $validated['target_month'] ?: \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m');
                        
                        $adjustmentExists = \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
                            ->where('target_payroll_month', $targetMonthStr)
                            ->where('amount_reversal', $netPayout)
                            ->exists();

                        if (!$adjustmentExists) {
                            \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::create([
                                'tenant_id'            => $employee->tenant_id,
                                'employee_id'          => $employee->id,
                                'target_payroll_month' => $targetMonthStr,
                                'reversal_days'        => 0,
                                'amount_reversal'      => $netPayout,
                                'status'               => 'pending',
                            ]);
                            $msg .= '. It has been added as Arrears for ' . $targetMonthStr . '.';
                        }
                    }
                }
            } else {
                $hold->update(['status' => 'on_hold']);
                $msg = 'Payout put on hold for ' . $employee->full_name;

                $calc = $this->payrollCalculationService->calculateSalary($employee, $month);
                $netPayout = $calc['summary']['net_payout'] ?? 0.00;

                \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->where('amount_reversal', $netPayout)
                    ->delete();
            }
        } else {
            $hold = PayrollHold::create([
                'employee_id'   => $employee->id,
                'payroll_month' => $month,
                'status'        => 'on_hold',
            ]);
            $msg = 'Payout put on hold for ' . $employee->full_name;
        }

        return $this->sendSuccess($hold, $msg);
    }

    /**
     * GET /api/hrms/payroll/my-salary
     */
    public function mySalary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->first();
        if (!$employee) {
            return $this->sendError('No employee profile linked to your user account.', 404);
        }

        $paidRuns = PayrollRun::where('status', 'paid')
            ->orderBy('payroll_month', 'desc')
            ->get();

        $salaryHistory = [];
        foreach ($paidRuns as $run) {
            $isHeld = PayrollHold::where('employee_id', $employee->id)
                ->where('payroll_month', $run->payroll_month)
                ->where('status', 'on_hold')
                ->exists();

            if ($isHeld) {
                continue;
            }

            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            $salaryHistory[] = [
                'payroll_run_id'=> $run->id,
                'payroll_month' => $run->payroll_month,
                'start_date'    => $run->start_date,
                'end_date'      => $run->end_date,
                'calc'          => $calc['summary'] ?? null,
                'details'       => $calc['items'] ?? [],
            ];
        }

        return $this->sendSuccess([
            'employee'       => $employee->load(['department', 'designation']),
            'salary_history' => $salaryHistory,
        ], 'Salary history loaded successfully.');
    }

    /**
     * POST /api/hrms/payroll/bulk-adhoc
     */
    public function storeBulkAdhoc(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'payroll_month'       => 'required|string|regex:/^\d{4}-\d{2}$/',
            'employee_ids'        => 'required|array',
            'employee_ids.*'      => 'exists:employees,id',
            'amount'              => 'required|numeric|min:0',
            'remarks'             => 'nullable|string',
        ]);

        $employees = Employee::whereIn('id', $validated['employee_ids'])->get();
        if ($employees->isEmpty()) {
            return $this->sendError('No selected employees found.', 404);
        }

        DB::beginTransaction();
        try {
            $insertData = [];
            foreach ($employees as $employee) {
                $insertData[] = [
                    'tenant_id'           => auth()->user()->tenant_id ?? 1,
                    'employee_id'         => $employee->id,
                    'salary_component_id' => $validated['salary_component_id'],
                    'amount'              => $validated['amount'],
                    'payroll_month'       => $validated['payroll_month'],
                    'status'              => 'pending',
                    'remarks'             => $validated['remarks'],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            DB::table('employee_adhoc_components')->insert($insertData);
            DB::commit();

            return $this->sendSuccess(null, 'Bulk ad-hoc adjustments created successfully for ' . $employees->count() . ' employees.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to create bulk adjustments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper for resolving pending issues.
     */
    private function getPendingIssues(PayrollRun $run): array
    {
        $startDate = $run->start_date;
        $endDate = $run->end_date;

        $leavesQuery = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        $correctionsQuery = AttendanceCorrection::with('employee')
            ->where('status', 'pending')
            ->whereBetween('date', [$startDate, $endDate]);

        $overtimeQuery = OvertimeRequest::with('employee')
            ->where('status', 'pending')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($run->employee_ids && count($run->employee_ids) > 0) {
            $leavesQuery->whereIn('employee_id', $run->employee_ids);
            $correctionsQuery->whereIn('employee_id', $run->employee_ids);
            $overtimeQuery->whereIn('employee_id', $run->employee_ids);
        }

        $pendingLeaves = $leavesQuery->get();
        $pendingCorrections = $correctionsQuery->get();
        $pendingOvertime = $overtimeQuery->get();

        return [
            'leaves'            => $pendingLeaves->count(),
            'corrections'       => $pendingCorrections->count(),
            'overtime'          => $pendingOvertime->count(),
            'total'             => $pendingLeaves->count() + $pendingCorrections->count() + $pendingOvertime->count(),
            'leaves_list'       => $pendingLeaves,
            'corrections_list'  => $pendingCorrections,
            'overtime_list'     => $pendingOvertime,
        ];
    }

    private function getOrCreateAccount(int $tenantId, string $code, string $name, string $type, string $normalBalance, ?string $subtype = null)
    {
        $account = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if ($account) {
            return $account;
        }

        $groupAccountIds = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->toArray();

        $account = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereNotIn('id', $groupAccountIds)
            ->first();

        if ($account) {
            return $account;
        }

        return \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->whereNotIn('id', $groupAccountIds)
            ->first();
    }
}
