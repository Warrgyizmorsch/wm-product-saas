<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\ExpenseReport;
use Carbon\Carbon;

class FnFCalculationService
{
    public function __construct(
        private readonly ?PayrollCalculationService $payrollCalculationService = null
    ) {}

    /**
     * Compute full and final settlement components for an exiting employee.
     */
    public function calculateFnF(EmployeeExit $exit): array
    {
        $employee = $exit->employee;
        $lwd = $exit->effective_lwd ?: Carbon::today();
        $lwdCarbon = Carbon::parse($lwd);
        
        $exitMonth = $lwdCarbon->format('Y-m');
        $startOfMonth = $lwdCarbon->copy()->startOfMonth();
        $daysInMonth = $lwdCarbon->daysInMonth;
        
        // 1. Calculate Monthly Salary & Daily Wage
        $monthlySalary = floatval($employee->current_salary ?: 0.00);
        // Annual CTC conversion if salary > 100,000 (often stored as annual in some orgs)
        $monthlyGross = $monthlySalary;
        if ($monthlySalary > 200000) {
            $monthlyGross = round($monthlySalary / 12.0, 2);
        }
        $dailyWage = round($monthlyGross / max(1, $daysInMonth), 2);

        // 2. Calculate Unpaid Days in Exit Month (from start of month up to LWD)
        $workedDaysInExitMonth = max(0, $startOfMonth->diffInDays($lwdCarbon) + 1);
        $unpaidSalaryAmount = round($dailyWage * $workedDaysInExitMonth, 2);

        // 3. Calculate Leave Encashment from unused earned balances
        $leaveBalances = LeaveBalance::where('employee_id', $employee->id)->with('leaveType')->get();
        $totalEncashableDays = 0.0;
        foreach ($leaveBalances as $bal) {
            $rem = floatval($bal->allocated) - floatval($bal->used) - floatval($bal->encashed ?? 0);
            if ($rem > 0) {
                $totalEncashableDays += $rem;
            }
        }
        $leaveEncashmentAmount = round($totalEncashableDays * $dailyWage, 2);

        // 4. Calculate Gratuity (Statutory: 15 days of last drawn salary for each completed year if tenure >= 5 years)
        $doj = $employee->date_of_joining ? Carbon::parse($employee->date_of_joining) : null;
        $gratuityAmount = 0.00;
        if ($doj) {
            $tenureYears = $doj->diffInYears($lwdCarbon);
            if ($tenureYears >= 5) {
                // (15 * Monthly Basic/Gross * Years) / 26
                $gratuityAmount = round((15 * $monthlyGross * $tenureYears) / 26.0, 2);
            }
        }

        // 5. Calculate Notice Period Shortfall Recovery
        $noticeShortfallDays = intval($exit->notice_shortfall_days ?? 0);
        $noticeShortfallRecovery = 0.00;
        if ($exit->notice_action === 'recover' && $noticeShortfallDays > 0) {
            $noticeShortfallRecovery = round($noticeShortfallDays * $dailyWage, 2);
        }

        // 6. Calculate Open Unsettled Cash Advances
        $unsettledAdvances = CashAdvance::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'disbursed'])
            ->sum('amount');
        $unsettledAdvancesRecovery = round(floatval($unsettledAdvances), 2);

        // 7. Calculate Asset Damage / Unreturned Deductions from Clearance Checklist
        $assetDeductions = $exit->clearances()
            ->where('deduction_amount', '>', 0)
            ->sum('deduction_amount');
        $assetDamageRecovery = round(floatval($assetDeductions), 2);

        // 8. Reconcile Approved Travel Reimbursements
        $pendingReimbursements = ExpenseReport::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('net_reimbursement');
        $otherEarnings = round(floatval($pendingReimbursements), 2);

        // 9. Totals Calculation
        $bonusAmount = 0.00;
        $otherDeductions = 0.00;

        $totalEarnings = round($unpaidSalaryAmount + $leaveEncashmentAmount + $gratuityAmount + $bonusAmount + $otherEarnings, 2);
        $totalDeductions = round($noticeShortfallRecovery + $unsettledAdvancesRecovery + $assetDamageRecovery + $otherDeductions, 2);
        $netPayable = round($totalEarnings - $totalDeductions, 2);

        return [
            'employee_id' => $employee->id,
            'employee_exit_id' => $exit->id,
            'calculation_date' => Carbon::today()->format('Y-m-d'),
            'lwd' => $lwdCarbon->format('Y-m-d'),
            'daily_wage' => $dailyWage,
            'unpaid_salary_days' => $workedDaysInExitMonth,
            'unpaid_salary_amount' => $unpaidSalaryAmount,
            'leave_encashment_days' => $totalEncashableDays,
            'leave_encashment_amount' => $leaveEncashmentAmount,
            'gratuity_amount' => $gratuityAmount,
            'bonus_amount' => $bonusAmount,
            'other_earnings' => $otherEarnings,
            'total_earnings' => $totalEarnings,
            'notice_shortfall_days' => $noticeShortfallDays,
            'notice_shortfall_recovery' => $noticeShortfallRecovery,
            'unsettled_advances_recovery' => $unsettledAdvancesRecovery,
            'asset_damage_recovery' => $assetDamageRecovery,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_payable_amount' => $netPayable,
            'status' => 'draft',
            'settlement_channel' => 'monthly_payroll',
        ];
    }

    /**
     * Save or update an FnF settlement record for an exit.
     */
    public function saveSettlement(EmployeeExit $exit, array $data): EmployeeFnfSettlement
    {
        $tenantId = $exit->tenant_id ?: tenant_id();

        $allowedColumns = [
            'calculation_date',
            'lwd',
            'unpaid_salary_days',
            'unpaid_salary_amount',
            'leave_encashment_days',
            'leave_encashment_amount',
            'gratuity_amount',
            'bonus_amount',
            'other_earnings',
            'total_earnings',
            'notice_shortfall_recovery',
            'unsettled_advances_recovery',
            'asset_damage_recovery',
            'other_deductions',
            'total_deductions',
            'net_payable_amount',
            'status',
            'settlement_channel',
            'payment_method',
            'payment_reference',
            'paid_at',
            'notes',
        ];

        $payload = array_intersect_key($data, array_flip($allowedColumns));

        return EmployeeFnfSettlement::updateOrCreate(
            [
                'employee_exit_id' => $exit->id,
            ],
            array_merge($payload, [
                'tenant_id' => $tenantId,
                'employee_id' => $exit->employee_id,
            ])
        );
    }

    /**
     * Helper to detect and get active exit FnF parameters during regular monthly payroll runs.
     */
    public function getFnFDataForMonthlyPayroll(Employee $employee, string $payrollMonth): ?array
    {
        $carbonMonth = Carbon::parse($payrollMonth . '-01');
        $startOfMonth = $carbonMonth->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $carbonMonth->copy()->endOfMonth()->format('Y-m-d');

        $activeExit = EmployeeExit::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'in_clearance', 'settled'])
            ->where(function($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('approved_lwd', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('preferred_lwd', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('resignation_date', [$startOfMonth, $endOfMonth]);
            })
            ->first();

        if (!$activeExit) {
            return null;
        }

        $settlement = $activeExit->fnfSettlement;
        if (!$settlement) {
            $computed = $this->calculateFnF($activeExit);
            $settlement = $this->saveSettlement($activeExit, $computed);
        }

        return [
            'exit' => $activeExit,
            'settlement' => $settlement,
            'lwd' => $activeExit->effective_lwd,
        ];
    }
}
