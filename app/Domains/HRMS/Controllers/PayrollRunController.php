<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\PayrollCalculationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Exports\PayrollBankExport;
use Maatwebsite\Excel\Facades\Excel;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollCalculationService $payrollCalculationService
    ) {}

    public function index(Request $request)
    {
        // Automatically run migrations to ensure schema is up-to-date
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

        $runs = PayrollRun::orderBy('payroll_month', 'desc')->get();
        
        $selectedRunId = $request->get('run_id');
        $selectedRun = $selectedRunId ? PayrollRun::find($selectedRunId) : $runs->first();

        // Get all active pay groups for the initiation form dropdown
        $payGroups = \App\Domains\HRMS\Models\PayGroup::where('status', true)->get();

        $registerData = [];
        $pendingPriorHolds = [];
        
        if ($selectedRun) {
            $excludeEmployeeIds = [];
            if (empty($selectedRun->employee_ids)) {
                // Exclude employees already processed in other runs for the same month
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

            // Get active employees mapped to pay groups & structures
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

                // 1. If the current run is a specific pay group, filter by it
                if ($selectedRun->pay_group_id) {
                    $employeesQuery->where('pay_group_id', $selectedRun->pay_group_id);
                } else {
                    // 2. If current run is "All Pay Groups", exclude any pay group that already has its own run
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

            // Fetch any unreleased holds from prior months to display warning alerts
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

        // Apply search, sort, and status/department filters to registerData
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

        $filters = [
            'search'        => $request->get('search'),
            'sort'          => $request->get('sort', 'name_asc'),
            'status'        => $request->get('status'),
            'department_id' => $request->get('department_id'),
        ];

        return view('modules.hrms.payroll.index', compact('runs', 'selectedRun', 'registerData', 'payGroups', 'pendingPriorHolds', 'pendingIssues', 'salaryComponents', 'allEmployees', 'departments', 'filters'));
    }

    public function storeRun(Request $request)
    {
        $validated = $request->validate([
            'payroll_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'pay_group_id'  => 'nullable|exists:pay_groups,id',
            'employee_ids'  => 'nullable|array',
            'employee_ids.*'=> 'exists:employees,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        // 1. If trying to create a specific run, check if a general run already exists for this month
        if (!empty($validated['pay_group_id'])) {
            $generalExists = PayrollRun::where('payroll_month', $validated['payroll_month'])
                ->whereNull('pay_group_id')
                ->where(function($q) {
                    $q->whereNull('employee_ids')->orWhere('employee_ids', '[]');
                })
                ->exists();
            if ($generalExists) {
                return redirect()->back()->with('error', "A general payroll run for all pay groups already exists for {$validated['payroll_month']}.");
            }
        }

        // 2. Check if a payroll run for this month and specific pay group already exists
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
                return redirect()->back()->with('error', "A payroll run already exists for {$validated['payroll_month']} and {$label}.");
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
                return redirect()->back()->with('error', "Cannot initiate payroll: The following employees have already been processed in another run for this month: " . implode(', ', $names));
            }
        }

        PayrollRun::create([
            'company_id'    => auth()->user()->company_id ?? 1,
            'pay_group_id'  => $validated['pay_group_id'] ?? null,
            'employee_ids'  => $validated['employee_ids'] ?? null,
            'payroll_month' => $validated['payroll_month'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'status'        => 'draft',
            'processed_by'  => auth()->id(),
        ]);

        return redirect()->route('hrms.payroll.index')->with('success', 'Payroll run initiated successfully.');
    }

    public function lockRun(PayrollRun $run)
    {
        $pending = $this->getPendingIssues($run);
        if ($pending['total'] > 0) {
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])
                ->with('error', "Cannot lock payroll: There are {$pending['total']} pending requests (Leaves: {$pending['leaves']}, Corrections: {$pending['corrections']}, Overtime: {$pending['overtime']}). Please resolve them first.");
        }

        $run->update(['status' => 'locked']);
        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', 'Payroll run locked successfully.');
    }

    public function resolvePending(Request $request, PayrollRun $run)
    {
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
                // 1. Approve Leave Requests
                $leaves = $leavesQuery->get();
                
                $leaveRepo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, ['action' => 'approved'], $request);
                }

                // 2. Approve Attendance Corrections
                $corrections = $correctionsQuery->get();

                $correctionController = app(\App\Domains\HRMS\Controllers\AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $correctionController->approve($request, $correction);
                }

                // 3. Approve Overtime Requests
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
                // reject_all
                // 1. Reject Leave Requests
                $leaves = $leavesQuery->get();
                
                $leaveRepo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, [
                        'action' => 'rejected',
                        'rejection_reason' => 'Auto-rejected during payroll run execution.'
                    ], $request);
                }

                // 2. Reject Attendance Corrections
                $corrections = $correctionsQuery->get();

                $correctionController = app(\App\Domains\HRMS\Controllers\AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $fakeRequest = clone $request;
                    $fakeRequest->merge(['rejected_reason' => 'Auto-rejected during payroll run execution.']);
                    $correctionController->reject($fakeRequest, $correction);
                }

                // 3. Reject Overtime Requests
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
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to auto-resolve payroll issues", [
                'run_id' => $run->id,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])
                ->with('error', "Failed to resolve pending requests: " . $e->getMessage());
        }
    }

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

    public function releasePayouts(PayrollRun $run)
    {
        $run->update(['status' => 'paid']);
        
        // Also update any pending retro adjustments inside this month to 'processed'
        DB::table('payroll_retroactive_adjustments')
            ->where('target_payroll_month', $run->payroll_month)
            ->where('status', 'pending')
            ->update(['status' => 'processed']);

        // Also update any pending ad-hoc components inside this month to 'processed'
        DB::table('employee_adhoc_components')
            ->where('payroll_month', $run->payroll_month)
            ->where('status', 'pending')
            ->update(['status' => 'processed']);

        $excludeEmployeeIds = [];
        if (empty($run->employee_ids)) {
            // Exclude employees already processed in other runs for the same month
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

        // Collect and update all approved overtime request records paid in this run
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
                \Illuminate\Support\Facades\Log::error("Payroll Payout Journal Posting Failed: " . $e->getMessage());
            }
        }

        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', 'Payroll payouts released successfully.');
    }

    public function toggleHold(Request $request, Employee $employee, string $month)
    {
        $hold = PayrollHold::where('employee_id', $employee->id)
            ->where('payroll_month', $month)
            ->first();

        if ($hold) {
            if ($hold->status === 'on_hold') {
                $hold->update(['status' => 'released']);
                $msg = 'Payout released for ' . $employee->full_name;

                // If the payroll run is already locked or paid, we cannot pay them directly in the current run.
                // Instead, we automatically convert this released payout to Arrears/Retroactive Adjustment for a target month (default: NEXT month).
                $run = PayrollRun::where('payroll_month', $month)->first();
                if ($run && in_array($run->status, ['locked', 'paid'])) {
                    $calc = $this->payrollCalculationService->calculateSalary($employee, $month);
                    $netPayout = $calc['summary']['net_payout'] ?? 0.00;

                    if ($netPayout > 0) {
                        // Use request's target_month if specified (e.g. from active cycle dashboard release), otherwise fallback to next month
                        $targetMonthStr = $request->get('target_month') ?: \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m');
                        
                        // Prevent duplicate adjustment entries
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

                // Clean up any pending retroactive adjustments that were created when this was released
                $calc = $this->payrollCalculationService->calculateSalary($employee, $month);
                $netPayout = $calc['summary']['net_payout'] ?? 0.00;

                \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->where('amount_reversal', $netPayout)
                    ->delete();
            }
        } else {
            PayrollHold::create([
                'employee_id'   => $employee->id,
                'payroll_month' => $month,
                'status'        => 'on_hold',
            ]);
            $msg = 'Payout put on hold for ' . $employee->full_name;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function mySalary()
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return redirect()->back()->with('error', 'No employee profile linked to your user account.');
        }

        // Get all paid runs (released payslips)
        $paidRuns = PayrollRun::where('status', 'paid')
            ->orderBy('payroll_month', 'desc')
            ->get();

        $salaryHistory = [];
        foreach ($paidRuns as $run) {
            // If the employee's payout is currently on hold, do not show the payslip in their employee portal
            $isHeld = PayrollHold::where('employee_id', $employee->id)
                ->where('payroll_month', $run->payroll_month)
                ->where('status', 'on_hold')
                ->exists();

            if ($isHeld) {
                continue;
            }

            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            $salaryHistory[] = [
                'run' => $run,
                'calc' => $calc['summary'] ?? null,
                'details' => $calc['items'] ?? [],
            ];
        }

        return view('modules.hrms.payroll.my_salary', compact('employee', 'salaryHistory'));
    }

    public function storeBulkAdhoc(Request $request)
    {
        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'payroll_month'       => 'required|string',
            'employee_ids'        => 'required|array',
            'employee_ids.*'      => 'exists:employees,id',
            'amount'              => 'required|numeric|min:0',
            'remarks'             => 'nullable|string',
        ]);

        $employees = Employee::whereIn('id', $validated['employee_ids'])->get();

        if ($employees->isEmpty()) {
            return redirect()->back()->with('error', 'No selected employees found.');
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
            return redirect()->back()->with('success', 'Bulk ad-hoc adjustments created successfully for ' . $employees->count() . ' employees.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create bulk adjustments: ' . $e->getMessage());
        }
    }

    public function exportBankFile(PayrollRun $run)
    {
        $fileName = 'bank_transfer_' . $run->payroll_month . '.xlsx';
        return Excel::download(new PayrollBankExport($run), $fileName);
    }

    public function downloadPayslip(PayrollRun $run, Employee $employee)
    {
        // Security check: If the logged-in user is an employee, they can only access their own payslip
        $user = auth()->user();
        if ($user->employee && $user->employee->id !== $employee->id) {
            abort(403, 'Unauthorized access to this payslip.');
        }

        $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
        $company = $employee->company ?? \App\Domains\HRMS\Models\Company::first();

        // Convert Net Payout to Words (Rupees)
        $netPayout = $calc['summary']['net_payout'] ?? 0;
        $netPayoutInWords = $this->convertNumberToWords($netPayout) . ' Only';

        $data = [
            'run' => $run,
            'employee' => $employee,
            'company' => $company,
            'calc' => $calc['summary'] ?? null,
            'details' => $calc['items'] ?? [],
            'netPayoutInWords' => $netPayoutInWords,
            'payroll_month_formatted' => \Carbon\Carbon::parse($run->payroll_month . '-01')->format('F Y'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('modules.hrms.payroll.pdf_payslip', $data);
        $fileName = 'payslip_' . $employee->employee_id . '_' . $run->payroll_month . '.pdf';
        
        return $pdf->download($fileName);
    }

    private function convertNumberToWords($number)
    {
        $no = (int)floor($number);
        $point = (int)round(($number - $no) * 100);
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = [];
        $words = [
            0 => '', 1 => 'One', 2 => 'Two',
            3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight',
            9 => 'Nine', 10 => 'Ten', 11 => 'Eleven',
            12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
            30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
            60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty',
            90 => 'Ninety'
        ];
        $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                    : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else {
                $str[] = null;
            }
        }
        $Rupees = implode('', array_reverse($str));
        $paise = '';
        if ($point > 0) {
            $paise = ' and ' . ($point < 21 ? $words[$point] : $words[floor($point / 10) * 10] . ' ' . $words[$point % 10]) . ' Paise';
        }
        return ($Rupees ? $Rupees . 'Rupees' : '') . $paise;
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
