<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Domains\HRMS\Models\EmployeeAdhocComponent;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\PayrollRetroactiveAdjustment;
use App\Domains\HRMS\Models\SalaryRevision;
use Carbon\Carbon;

class PayrollCalculationService
{
    /**
     * Compute final salary breakdown for an employee in a given month.
     *
     * @param Employee $employee
     * @param string $payrollMonth Format: YYYY-MM
     * @return array
     */
    public function calculateSalary(Employee $employee, string $payrollMonth): array
    {
        // 1. Check Hold Status
        $isHeld = PayrollHold::where('employee_id', $employee->id)
            ->where('payroll_month', $payrollMonth)
            ->where('status', 'on_hold')
            ->exists();

        if ($isHeld) {
            return [
                'success' => true,
                'status'  => 'on_hold',
                'message' => 'Payout for this employee is currently withheld on hold.',
                'summary' => [
                    'employee_name' => $employee->full_name,
                    'net_payout'    => 0.00
                ]
            ];
        }

        $carbonMonth = Carbon::parse($payrollMonth . '-01');
        $totalDaysInMonth = $carbonMonth->daysInMonth;

        // 2. Resolve Pay Group Rules
        $payGroup = $employee->payGroup;
        $rules = $payGroup ? ($payGroup->payroll_rules ?? []) : [];
        $prorationRule = $rules['proration_rule'] ?? 'calendar_days';
        $splicingRule = $rules['lop_splicing_rule'] ?? 'proportionate_gross';

        // 3. Resolve Divisor for Daily Wage
        $divisor = $totalDaysInMonth;
        if ($prorationRule === 'fixed_30_days') {
            $divisor = 30;
        } elseif ($prorationRule === 'working_days') {
            // Count days in month excluding Sundays (0)
            $workingDays = 0;
            for ($d = 1; $d <= $totalDaysInMonth; $d++) {
                $tempDate = Carbon::parse($payrollMonth . '-' . $d);
                if ($tempDate->dayOfWeek !== Carbon::SUNDAY) {
                    $workingDays++;
                }
            }
            $divisor = max(1, $workingDays);
        }

        // 4. Calculate LOP Days (Absences not excused/excused corrections)
        $lopDays = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', $carbonMonth->month)
            ->whereYear('date', $carbonMonth->year)
            ->where('status', 'absent')
            ->count();

        // 5. Check for mid-month CTC split/revision
        $revisions = SalaryRevision::where('employee_id', $employee->id)
            ->whereMonth('effective_date', $carbonMonth->month)
            ->whereYear('effective_date', $carbonMonth->year)
            ->orderBy('effective_date', 'asc')
            ->get();

        $computedSalaryItems = [];
        $baseStructure = $employee->salaryStructure;

        if ($baseStructure && $baseStructure->items) {
            foreach ($baseStructure->items as $item) {
                $component = $item->component;
                // Annual CTC / 12 = Base Monthly Value
                $yearlyValue = 0.00;

                // Handle percentage-based values
                if ($item->calculation_type === 'percentage_of_ctc') {
                    $yearlyValue = ($employee->current_salary * $item->value) / 100;
                } elseif ($item->calculation_type === 'percentage_of_basic') {
                    // Find basic component item
                    $basicItem = $baseStructure->items->filter(fn($i) => $i->component->code === 'BASIC')->first();
                    if ($basicItem) {
                        $basicYearly = ($employee->current_salary * $basicItem->value) / 100;
                        $yearlyValue = ($basicYearly * $item->value) / 100;
                    }
                } elseif ($item->calculation_type === 'fixed') {
                    $yearlyValue = $item->value * 12;
                } elseif ($item->calculation_type === 'balancing') {
                    // Will calculate remainder below
                    $yearlyValue = 0.00;
                }

                $monthlyValue = round($yearlyValue / 12, 2);

                $computedSalaryItems[$component->code] = [
                    'name'             => $component->name,
                    'type'             => $component->type,
                    'base_monthly'     => $monthlyValue,
                    'calculated_value' => $monthlyValue,
                    'deduction'        => 0.00,
                    'reversal'         => 0.00,
                ];
            }

            // Calculate Balancing Component (e.g. Special Allowance)
            $balancingItem = $baseStructure->items->filter(fn($i) => $i->calculation_type === 'balancing')->first();
            if ($balancingItem) {
                $compCode = $balancingItem->component->code;
                $monthlyCTC = $employee->current_salary / 12;
                $sumOthers = 0.00;
                foreach ($computedSalaryItems as $code => $data) {
                    if ($code !== $compCode && $data['type'] === 'earning') {
                        $sumOthers += $data['base_monthly'];
                    }
                }
                $balancingMonthly = max(0, $monthlyCTC - $sumOthers);
                if (isset($computedSalaryItems[$compCode])) {
                    $computedSalaryItems[$compCode]['base_monthly'] = round($balancingMonthly, 2);
                    $computedSalaryItems[$compCode]['calculated_value'] = round($balancingMonthly, 2);
                }
            }
        }

        // 6. Apply LOP deductions
        $lopFactor = $divisor > 0 ? ($lopDays / $divisor) : 0;
        foreach ($computedSalaryItems as $code => &$item) {
            if ($item['type'] === 'earning' && $lopFactor > 0) {
                if ($splicingRule === 'proportionate_gross' || in_array($code, ['BASIC', 'HRA'])) {
                    $item['deduction'] = round($item['base_monthly'] * $lopFactor, 2);
                    $item['calculated_value'] = max(0.00, $item['calculated_value'] - $item['deduction']);
                }
            }
        }
        unset($item);

        // 7. Inject Ad-hoc Variable Components
        $adhocs = EmployeeAdhocComponent::with(['component'])
            ->where('employee_id', $employee->id)
            ->where('payroll_month', $payrollMonth)
            ->get();

        $adhocEarnings = 0.00;
        $adhocDeductions = 0.00;
        foreach ($adhocs as $adhoc) {
            $code = $adhoc->component->code;
            $type = $adhoc->component->type;
            if ($type === 'earning') {
                $adhocEarnings += $adhoc->amount;
            } else {
                $adhocDeductions += $adhoc->amount;
            }
        }

        // 8. Inject Unexcused Attendance Penalties
        $penalties = EmployeePenalty::where('employee_id', $employee->id)
            ->whereMonth('date', $carbonMonth->month)
            ->whereYear('date', $carbonMonth->year)
            ->where('status', '!=', 'excused')
            ->sum('penalty_amount');

        // 9. Process Retro LOP Adjustments (Reversals)
        $retroAdjustments = PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->get();

        $totalRetroReversal = 0.00;
        foreach ($retroAdjustments as $adj) {
            $totalRetroReversal += $adj->amount_reversal;
        }

        // 10. Compute final sums
        $grossEarnings = 0.00;
        $grossDeductions = 0.00;

        foreach ($computedSalaryItems as $code => $data) {
            if ($data['type'] === 'earning') {
                $grossEarnings += $data['calculated_value'];
            } else {
                $grossDeductions += $data['calculated_value'];
            }
        }

        $finalEarnings = $grossEarnings + $adhocEarnings + $totalRetroReversal;
        $finalDeductions = $grossDeductions + $adhocDeductions + $penalties;
        $netPayout = max(0.00, $finalEarnings - $finalDeductions);

        return [
            'success' => true,
            'status'  => 'calculated',
            'summary' => [
                'employee_name'        => $employee->display_name,
                'payroll_month'        => $payrollMonth,
                'total_days'           => $totalDaysInMonth,
                'lop_days'             => $lopDays,
                'proration_rule'       => $prorationRule,
                'base_gross_earnings'  => round($grossEarnings, 2),
                'adhoc_earnings'       => round($adhocEarnings, 2),
                'retro_lop_reversals'  => round($totalRetroReversal, 2),
                'total_earnings'       => round($finalEarnings, 2),
                'base_deductions'      => round($grossDeductions, 2),
                'adhoc_deductions'     => round($adhocDeductions, 2),
                'attendance_penalties' => round($penalties, 2),
                'total_deductions'     => round($finalDeductions, 2),
                'net_payout'           => round($netPayout, 2),
            ],
            'items' => $computedSalaryItems,
        ];
    }
}
