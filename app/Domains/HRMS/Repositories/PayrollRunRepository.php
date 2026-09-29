<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\Accounting\Services\PayrollAccountingService;
use App\Domains\HRMS\Controllers\AttendanceCorrectionController;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\PayrollRetroactiveAdjustment;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Services\DocumentTemplateService;
use App\Domains\HRMS\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollRunRepository implements PayrollRunRepositoryInterface
{
    public function __construct(
        private readonly PayrollCalculationService $payrollCalculationService
    ) {}

    public function getIndexData(array $inputs): array
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {}

        $runs = PayrollRun::orderBy('payroll_month', 'desc')->get();
        
        $selectedRunId = $inputs['run_id'] ?? null;
        $selectedRun = $selectedRunId ? PayrollRun::find($selectedRunId) : $runs->first();

        $payGroups = PayGroup::where('status', true)->get();

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
                        $genEmpIds = Employee::where('status', true)->whereNotNull('pay_group_id')->pluck('id')->toArray();
                        $excludeEmployeeIds = array_merge($excludeEmployeeIds, $genEmpIds);
                    }
                }
                $excludeEmployeeIds = array_unique($excludeEmployeeIds);
            }

            if ($selectedRun->employee_ids && count($selectedRun->employee_ids) > 0) {
                $employees = Employee::whereIn('id', $selectedRun->employee_ids)
                    ->whereNotIn('id', $excludeEmployeeIds)
                    ->where('status', true)
                    ->whereNotNull('pay_group_id')
                    ->get();
            } else {
                $employeesQuery = Employee::where('status', true)
                    ->whereNotNull('pay_group_id')
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
                        'employee_name'           => $employee->full_name,
                        'total_earnings'          => 0.00,
                        'total_deductions'        => 0.00,
                        'lop_days'                => 0,
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
        $salaryComponents = SalaryComponent::where('status', true)
            ->where('is_adhoc', true)
            ->get();
        $allEmployees = Employee::where('status', true)->orderBy('full_name')->get();
        $departments = Department::orderBy('name')->get();

        $registerCollection = collect($registerData);
        
        $search = $inputs['search'] ?? null;
        if ($search) {
            $search = strtolower(trim($search));
            $registerCollection = $registerCollection->filter(function($row) use ($search) {
                $name = strtolower($row['employee']->full_name);
                $empId = strtolower($row['employee']->employee_id);
                return str_contains($name, $search) || str_contains($empId, $search);
            });
        }

        $status = $inputs['status'] ?? null;
        if ($status && $selectedRun) {
            $registerCollection = $registerCollection->filter(function($row) use ($status, $selectedRun) {
                if ($status === 'held') {
                    return $selectedRun->status === 'paid' ? ($row['hold_status'] === 'on_hold') : $row['is_held'];
                } elseif ($status === 'approved') {
                    return $selectedRun->status === 'paid' ? ($row['hold_status'] !== 'on_hold') : !$row['is_held'];
                }
                return true;
            });
        }

        $deptId = $inputs['department_id'] ?? null;
        if ($deptId) {
            $registerCollection = $registerCollection->filter(function($row) use ($deptId) {
                return $row['employee']->department_id == $deptId;
            });
        }

        $sort = $inputs['sort'] ?? 'name_asc';
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
            'search'        => $inputs['search'] ?? null,
            'sort'          => $inputs['sort'] ?? 'name_asc',
            'status'        => $inputs['status'] ?? null,
            'department_id' => $inputs['department_id'] ?? null,
        ];

        return compact('runs', 'selectedRun', 'registerData', 'payGroups', 'pendingPriorHolds', 'pendingIssues', 'salaryComponents', 'allEmployees', 'departments', 'filters');
    }

    public function storeRun(array $validated): array
    {
        if (!empty($validated['pay_group_id'])) {
            $generalExists = PayrollRun::where('payroll_month', $validated['payroll_month'])
                ->whereNull('pay_group_id')
                ->where(function($q) {
                    $q->whereNull('employee_ids')->orWhere('employee_ids', '[]');
                })
                ->exists();
            if ($generalExists) {
                return ['success' => false, 'message' => "A general payroll run for all pay groups already exists for {$validated['payroll_month']}."];
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
                return ['success' => false, 'message' => "A payroll run already exists for {$validated['payroll_month']} and {$label}."];
            }
        } else {
            $otherRuns = PayrollRun::where('payroll_month', $validated['payroll_month'])->get();
            $alreadyProcessed = [];
            foreach ($otherRuns as $or) {
                if ($or->employee_ids && count($or->employee_ids) > 0) {
                    $alreadyProcessed = array_merge($alreadyProcessed, $or->employee_ids);
                } elseif ($or->pay_group_id) {
                    $pgEmpIds = Employee::where('pay_group_id', $or->pay_group_id)->pluck('id')->toArray();
                    $alreadyProcessed = array_merge($alreadyProcessed, $pgEmpIds);
                } else {
                    $genEmpIds = Employee::where('status', true)->whereNotNull('pay_group_id')->pluck('id')->toArray();
                    $alreadyProcessed = array_merge($alreadyProcessed, $genEmpIds);
                }
            }
            $alreadyProcessed = array_unique($alreadyProcessed);
            $overlap = array_intersect($validated['employee_ids'], $alreadyProcessed);
            if (!empty($overlap)) {
                $names = Employee::whereIn('id', $overlap)->pluck('full_name')->toArray();
                return ['success' => false, 'message' => "Cannot initiate payroll: The following employees have already been processed in another run for this month: " . implode(', ', $names)];
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

        return ['success' => true, 'run' => $run];
    }

    public function lockRun(PayrollRun $run): array
    {
        $pending = $this->getPendingIssues($run);
        if ($pending['total'] > 0) {
            return [
                'success' => false,
                'message' => "Cannot lock payroll: There are {$pending['total']} pending requests (Leaves: {$pending['leaves']}, Corrections: {$pending['corrections']}, Overtime: {$pending['overtime']}). Please resolve them first."
            ];
        }

        $run->update(['status' => 'locked']);

        try {
            $payrollAccountingService = app(PayrollAccountingService::class);
            $payrollAccountingService->postPayrollRunJournal($run);
        } catch (\Throwable $e) {
            Log::error("Failed to post payroll accrual journal on lock: " . $e->getMessage());
        }

        return ['success' => true];
    }

    public function resolvePending(PayrollRun $run, string $resolution, Request $request): array
    {
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
                $leaveRepo = app(LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, ['action' => 'approved'], $request);
                }

                $corrections = $correctionsQuery->get();
                $correctionController = app(AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $correctionController->approve($request, $correction);
                }

                $overtimes = $overtimesQuery->get();
                $otRepo = app(OvertimeRequestRepositoryInterface::class);
                foreach ($overtimes as $ot) {
                    $otRepo->updateStatus($ot, [
                        'action' => 'approved',
                        'approved_duration_hours' => $ot->duration_hours
                    ], $request);
                }

                $message = "All pending requests approved and payroll calculations updated.";
            } else {
                $leaves = $leavesQuery->get();
                $leaveRepo = app(LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, [
                        'action' => 'rejected',
                        'rejection_reason' => 'Auto-rejected during payroll run execution.'
                    ], $request);
                }

                $corrections = $correctionsQuery->get();
                $correctionController = app(AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $fakeRequest = clone $request;
                    $fakeRequest->merge(['rejected_reason' => 'Auto-rejected during payroll run execution.']);
                    $correctionController->reject($fakeRequest, $correction);
                }

                $overtimes = $overtimesQuery->get();
                $otRepo = app(OvertimeRequestRepositoryInterface::class);
                foreach ($overtimes as $ot) {
                    $otRepo->updateStatus($ot, [
                        'action' => 'rejected',
                        'rejection_reason' => 'Auto-rejected during payroll run execution.'
                    ], $request);
                }

                $message = "All pending requests rejected. Unresolved days calculated as LOP/zeros.";
            }

            DB::commit();
            return ['success' => true, 'message' => $message];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to auto-resolve payroll issues", [
                'run_id' => $run->id,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => "Failed to resolve pending requests: " . $e->getMessage()];
        }
    }

    public function releasePayouts(PayrollRun $run): void
    {
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
                ->whereNotNull('pay_group_id')
                ->get();
        } else {
            $employeesQuery = Employee::where('status', true)
                ->whereNotNull('pay_group_id')
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

        try {
            $payrollAccountingService = app(PayrollAccountingService::class);
            $payrollAccountingService->postPayrollRunJournal($run);
            $payrollAccountingService->postPayrollPayoutJournal($run);
        } catch (\Throwable $e) {
            Log::error("Failed to post payroll journal entry: " . $e->getMessage(), [
                'run_id' => $run->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function toggleHold(Employee $employee, string $month, ?string $targetMonth): string
    {
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
                        $targetMonthStr = $targetMonth ?: Carbon::parse($month . '-01')->addMonth()->format('Y-m');
                        
                        $adjustmentExists = PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
                            ->where('target_payroll_month', $targetMonthStr)
                            ->where('amount_reversal', $netPayout)
                            ->exists();

                        if (!$adjustmentExists) {
                            PayrollRetroactiveAdjustment::create([
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

                PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
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

        return $msg;
    }

    public function getMySalaryData(Employee $employee): array
    {
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
                'run' => $run,
                'calc' => $calc['summary'] ?? null,
                'details' => $calc['items'] ?? [],
            ];
        }

        return compact('employee', 'salaryHistory');
    }

    public function storeBulkAdhoc(array $validated): array
    {
        $employees = Employee::whereIn('id', $validated['employee_ids'])->get();

        if ($employees->isEmpty()) {
            return ['success' => false, 'message' => 'No selected employees found.'];
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
                    'remarks'             => $validated['remarks'] ?? null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            DB::table('employee_adhoc_components')->insert($insertData);

            DB::commit();
            return ['success' => true, 'count' => $employees->count()];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to create bulk adjustments: ' . $e->getMessage()];
        }
    }

    public function getPayslipData(PayrollRun $run, Employee $employee): array
    {
        $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
        $company = $employee->company ?? \App\Domains\HRMS\Models\Company::first();

        $netPayout = $calc['summary']['net_payout'] ?? 0;
        $netPayoutInWords = $this->convertNumberToWords($netPayout) . ' Only';

        return [
            'run' => $run,
            'employee' => $employee,
            'company' => $company,
            'calc' => $calc['summary'] ?? null,
            'details' => $calc['items'] ?? [],
            'netPayoutInWords' => $netPayoutInWords,
            'payroll_month_formatted' => Carbon::parse($run->payroll_month . '-01')->format('F Y'),
        ];
    }

    public function getPendingIssues(PayrollRun $run): array
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

    private function convertNumberToWords($number): string
    {
        $no = (int)floor($number);
        $point = (int)round(($number - $no) * 100);
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
}
