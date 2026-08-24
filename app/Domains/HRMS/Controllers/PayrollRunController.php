<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\PayrollCalculationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollCalculationService $payrollCalculationService
    ) {}

    public function index(Request $request)
    {
        $runs = PayrollRun::orderBy('payroll_month', 'desc')->get();
        
        $selectedRunId = $request->get('run_id');
        $selectedRun = $selectedRunId ? PayrollRun::find($selectedRunId) : $runs->first();

        // Get all active pay groups for the initiation form dropdown
        $payGroups = \App\Domains\HRMS\Models\PayGroup::where('status', true)->get();

        $registerData = [];
        $pendingPriorHolds = [];
        
        if ($selectedRun) {
            // Get active employees mapped to pay groups & structures
            $employeesQuery = Employee::where('status', true)
                ->whereNotNull('pay_group_id')
                ->whereNotNull('salary_structure_id');

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

        return view('modules.hrms.payroll.index', compact('runs', 'selectedRun', 'registerData', 'payGroups', 'pendingPriorHolds'));
    }

    public function storeRun(Request $request)
    {
        $validated = $request->validate([
            'payroll_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'pay_group_id'  => 'nullable|exists:pay_groups,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        // 1. If trying to create a specific run, check if a general run already exists for this month
        if (!empty($validated['pay_group_id'])) {
            $generalExists = PayrollRun::where('payroll_month', $validated['payroll_month'])
                ->whereNull('pay_group_id')
                ->exists();
            if ($generalExists) {
                return redirect()->back()->with('error', "A general payroll run for all pay groups already exists for {$validated['payroll_month']}.");
            }
        }

        // 2. Check if a payroll run for this month and specific pay group already exists
        $existsQuery = PayrollRun::where('payroll_month', $validated['payroll_month']);
        if (!empty($validated['pay_group_id'])) {
            $existsQuery->where('pay_group_id', $validated['pay_group_id']);
        } else {
            $existsQuery->whereNull('pay_group_id');
        }

        if ($existsQuery->exists()) {
            $label = !empty($validated['pay_group_id']) ? 'this pay group' : 'all pay groups';
            return redirect()->back()->with('error', "A payroll run already exists for {$validated['payroll_month']} and {$label}.");
        }

        PayrollRun::create([
            'company_id'    => auth()->user()->company_id ?? 1,
            'pay_group_id'  => $validated['pay_group_id'] ?? null,
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
        $run->update(['status' => 'locked']);
        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', 'Payroll run locked successfully.');
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

        // Collect and update all approved overtime request records paid in this run
        $employeesQuery = Employee::where('status', true)
            ->whereNotNull('pay_group_id')
            ->whereNotNull('salary_structure_id');

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

        $allOtIds = [];
        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            if (!empty($calc['summary']['processed_ot_ids'])) {
                $allOtIds = array_merge($allOtIds, $calc['summary']['processed_ot_ids']);
            }
        }

        if (!empty($allOtIds)) {
            DB::table('overtime_requests')
                ->whereIn('id', $allOtIds)
                ->update(['status' => 'processed']);
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
}
