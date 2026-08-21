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

        $registerData = [];
        if ($selectedRun) {
            // Get all active employees mapped to pay groups & structures
            $employees = Employee::where('status', true)
                ->whereNotNull('pay_group_id')
                ->whereNotNull('salary_structure_id')
                ->get();

            foreach ($employees as $employee) {
                $calc = $this->payrollCalculationService->calculateSalary($employee, $selectedRun->payroll_month);
                $isHeld = PayrollHold::where('employee_id', $employee->id)
                    ->where('payroll_month', $selectedRun->payroll_month)
                    ->where('status', 'on_hold')
                    ->exists();

                $registerData[] = [
                    'employee' => $employee,
                    'is_held'  => $isHeld,
                    'calc'     => $calc['summary'] ?? [
                        'employee_name'        => $employee->full_name,
                        'total_earnings'       => 0.00,
                        'total_deductions'     => 0.00,
                        'lop_days'             => 0,
                        'attendance_penalties' => 0.00,
                        'net_payout'           => 0.00,
                    ],
                ];
            }
        }

        return view('modules.hrms.payroll.index', compact('runs', 'selectedRun', 'registerData'));
    }

    public function storeRun(Request $request)
    {
        $validated = $request->validate([
            'payroll_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        // Check if run already exists
        $exists = PayrollRun::where('payroll_month', $validated['payroll_month'])->exists();
        if ($exists) {
            return redirect()->back()->with('error', 'A payroll run already exists for ' . $validated['payroll_month'] . '.');
        }

        PayrollRun::create([
            'company_id'    => auth()->user()->company_id ?? 1,
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
            } else {
                $hold->update(['status' => 'on_hold']);
                $msg = 'Payout put on hold for ' . $employee->full_name;
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
