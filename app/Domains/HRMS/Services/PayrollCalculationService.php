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

        // 4. Calculate LOP Days (Absences + Penalty Days combined)
        $absenceDays = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', $carbonMonth->month)
            ->whereYear('date', $carbonMonth->year)
            ->where('status', 'absent')
            ->count();

        $penaltyDaysCount = EmployeePenalty::where('employee_id', $employee->id)
            ->whereMonth('date', $carbonMonth->month)
            ->whereYear('date', $carbonMonth->year)
            ->where('status', '!=', 'excused')
            ->sum('penalty_amount');

        $lopDays = $absenceDays + $penaltyDaysCount;

        // 5. Check for mid-month CTC split/revision
        $revisions = SalaryRevision::where('employee_id', $employee->id)
            ->whereMonth('effective_date', $carbonMonth->month)
            ->whereYear('effective_date', $carbonMonth->year)
            ->orderBy('effective_date', 'asc')
            ->get();

        $computedSalaryItems = [];
        
        $baseStructure = null;
        if ($employee->payGroup) {
            $baseStructure = \App\Domains\HRMS\Models\SalaryStructure::where('pay_group_id', $employee->pay_group_id)
                ->where('min_ctc', '<=', $employee->current_salary)
                ->where('max_ctc', '>=', $employee->current_salary)
                ->where('status', true)
                ->first();
        }
        if (!$baseStructure) {
            $baseStructure = $employee->salaryStructure;
        }

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

        // 8. Inject Unexcused Attendance Penalties (stored as number of days, e.g. 0.25 days, 0.5 days)
        $penaltyDays = EmployeePenalty::where('employee_id', $employee->id)
            ->whereMonth('date', $carbonMonth->month)
            ->whereYear('date', $carbonMonth->year)
            ->where('status', '!=', 'excused')
            ->sum('penalty_amount');

        $dailyWage = ($employee->current_salary / 12) / $divisor;
        // Since penalty days are now added directly to LOP days above, we set penalty money deduction to 0.00 to avoid double-counting.
        $penaltyDeductionAmount = 0.00;

        // 9. Process Retro LOP Adjustments (Reversals)
        $retroAdjustments = PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
            ->where(function($query) use ($payrollMonth) {
                $query->where(function($q) use ($payrollMonth) {
                    $q->where('status', 'pending')
                      ->where('target_payroll_month', '<=', $payrollMonth);
                })->orWhere(function($q) use ($payrollMonth) {
                    $q->where('status', 'processed')
                      ->where('target_payroll_month', $payrollMonth);
                });
            })
            ->get();

        $totalRetroReversal = 0.00;
        foreach ($retroAdjustments as $adj) {
            $totalRetroReversal += $adj->amount_reversal;
        }

        // 9.5. Process Overtime Payouts
        $overtimeRequests = \App\Domains\HRMS\Models\OvertimeRequest::where('employee_id', $employee->id)
            ->whereBetween('date', [$carbonMonth->copy()->startOfMonth(), $carbonMonth->copy()->endOfMonth()])
            ->whereIn('status', ['approved', 'processed'])
            ->whereIn('compensation_type', ['payout', 'pay'])
            ->get();

        $divisorValue = $divisor > 0 ? $divisor : $totalDaysInMonth;
        $dailyWage = ($employee->current_salary / 12) / $divisorValue;

        $standardShiftHours = 8.0;
        $defaultShift = $employee->defaultShift;
        if ($defaultShift) {
            $shiftStart = Carbon::parse($defaultShift->start_time);
            $shiftEnd = Carbon::parse($defaultShift->end_time);
            if ($shiftEnd->lessThan($shiftStart)) {
                $shiftEnd->addDay();
            }
            $shiftDurationMinutes = $shiftStart->diffInMinutes($shiftEnd) - ($defaultShift->break_minutes ?? 0);
            $standardShiftHours = max(1.0, $shiftDurationMinutes / 60.0);
        }
        
        $hourlyWage = $dailyWage / $standardShiftHours;

        $otRule = \App\Domains\HRMS\Models\AttendancePenalty::where('rule_type', 'overtime_rules')
            ->where('company_id', $employee->company_id)
            ->where('status', true)
            ->first();

        if (!$otRule) {
            $otRule = \App\Domains\HRMS\Models\AttendancePenalty::where('rule_type', 'overtime_rules')
                ->whereNull('company_id')
                ->where('status', true)
                ->first();
        }

        $tenantSettings = [];
        if ($otRule && is_array($otRule->penalty_tiers)) {
            $tenantSettings = $otRule->penalty_tiers;
        } else {
            $tenant = \App\Models\Tenant::find($employee->tenant_id);
            if ($tenant && is_array($tenant->settings)) {
                $tenantSettings = $tenant->settings;
            }
        }

        $maxMonthlyHours = $tenantSettings['overtime_max_monthly_hours'] ?? null;
        $weekendMultiplier = $tenantSettings['overtime_weekend_multiplier'] ?? 2.0;
        $holidayMultiplier = $tenantSettings['overtime_holiday_multiplier'] ?? 2.5;
        $otTiers = $tenantSettings['overtime_tiers'] ?? [
            ['min_hours' => 0, 'max_hours' => 2, 'multiplier' => 1.5],
            ['min_hours' => 2, 'max_hours' => null, 'multiplier' => 2.0]
        ];

        $accumulatedOtHours = 0.0;
        $totalOtPayout = 0.00;
        $processedOtIds = [];

        foreach ($overtimeRequests as $ot) {
            $duration = (float) $ot->approved_duration_hours;
            if ($duration <= 0) {
                continue;
            }

            if ($maxMonthlyHours !== null) {
                if ($accumulatedOtHours >= $maxMonthlyHours) {
                    continue;
                }
                if ($accumulatedOtHours + $duration > $maxMonthlyHours) {
                    $duration = $maxMonthlyHours - $accumulatedOtHours;
                }
            }

            $accumulatedOtHours += $duration;
            $processedOtIds[] = $ot->id;

            $dateStr = $ot->date->format('Y-m-d');
            $isHoliday = \App\Domains\Production\Models\ProductionCalendarHoliday::whereDate('holiday_date', $dateStr)->exists();

            if ($isHoliday) {
                $payout = $duration * $hourlyWage * $holidayMultiplier;
            } else {
                $isWeekend = false;
                $activeShift = $employee->resolveShiftForDate($dateStr);
                if (!$activeShift) {
                    $isWeekend = true;
                }

                if ($isWeekend) {
                    $payout = $duration * $hourlyWage * $weekendMultiplier;
                } else {
                    $payout = 0.00;
                    $remainingHours = $duration;

                    usort($otTiers, function($a, $b) {
                        return $a['min_hours'] <=> $b['min_hours'];
                    });

                    foreach ($otTiers as $tier) {
                        $min = (float) $tier['min_hours'];
                        $max = isset($tier['max_hours']) ? (float) $tier['max_hours'] : INF;
                        $mult = (float) $tier['multiplier'];

                        if ($duration > $min) {
                            $tierSpan = $max - $min;
                            $hoursInThisTier = min($remainingHours, $tierSpan);
                            if ($hoursInThisTier > 0) {
                                $payout += $hoursInThisTier * $hourlyWage * $mult;
                                $remainingHours -= $hoursInThisTier;
                            }
                        }
                    }
                    if ($remainingHours > 0) {
                        $payout += $remainingHours * $hourlyWage * 1.5;
                    }
                }
            }

            $totalOtPayout += round($payout, 2);
        }

        // 9.75. Process Leave Encashments
        $leaveEncashments = \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'processed'])
            ->whereMonth('approved_at', $carbonMonth->month)
            ->whereYear('approved_at', $carbonMonth->year)
            ->get();

        $totalEncashmentPayout = 0.00;
        $totalEncashmentDays = 0.0;
        $processedEncashIds = [];

        $monthlyBasic = $computedSalaryItems['BASIC']['base_monthly'] ?? (($employee->current_salary * 0.5) / 12);
        $divisorValue = $divisor > 0 ? $divisor : $totalDaysInMonth;
        $dailyBasicRate = $monthlyBasic / $divisorValue;

        foreach ($leaveEncashments as $encash) {
            $days = (float) $encash->requested_days;
            if ($days <= 0) {
                continue;
            }
            $payout = $days * $dailyBasicRate;
            $totalEncashmentPayout += round($payout, 2);
            $totalEncashmentDays += $days;
            $processedEncashIds[] = $encash->id;
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

        $finalEarnings = $grossEarnings + $adhocEarnings + $totalRetroReversal + $totalOtPayout + $totalEncashmentPayout;
        $finalDeductions = $grossDeductions + $adhocDeductions + $penaltyDeductionAmount;
        $netPayout = max(0.00, $finalEarnings - $finalDeductions);

        // Inject adhoc components and retro adjustments into computedSalaryItems after sum calculations to prevent double-counting
        foreach ($adhocs as $adhoc) {
            if ($adhoc->component) {
                $computedSalaryItems[$adhoc->component->code] = [
                    'name'             => $adhoc->component->name,
                    'type'             => $adhoc->component->type,
                    'base_monthly'     => $adhoc->amount,
                    'calculated_value' => $adhoc->amount,
                    'deduction'        => 0.00,
                    'reversal'         => 0.00,
                ];
            }
        }

        foreach ($retroAdjustments as $adj) {
            $computedSalaryItems['RETRO_REFUND_' . $adj->id] = [
                'name'             => 'Retroactive Backpay (' . Carbon::parse($adj->target_payroll_month . '-01')->format('F Y') . ')',
                'type'             => 'earning',
                'base_monthly'     => $adj->amount_reversal,
                'calculated_value' => $adj->amount_reversal,
                'deduction'        => 0.00,
                'reversal'         => 0.00,
            ];
        }

        if ($totalOtPayout > 0) {
            $computedSalaryItems['OVERTIME'] = [
                'name'             => 'Overtime Pay (' . number_format($accumulatedOtHours, 1) . ' hrs)',
                'type'             => 'earning',
                'base_monthly'     => $totalOtPayout,
                'calculated_value' => $totalOtPayout,
                'deduction'        => 0.00,
                'reversal'         => 0.00,
            ];
        }

        if ($totalEncashmentPayout > 0) {
            $computedSalaryItems['LEAVE_ENCASHMENT'] = [
                'name'             => 'Leave Encashment (' . number_format($totalEncashmentDays, 1) . ' days)',
                'type'             => 'earning',
                'base_monthly'     => $totalEncashmentPayout,
                'calculated_value' => $totalEncashmentPayout,
                'deduction'        => 0.00,
                'reversal'         => 0.00,
            ];
        }

        $isHeld = PayrollHold::where('employee_id', $employee->id)
            ->where('payroll_month', $payrollMonth)
            ->where('status', 'on_hold')
            ->exists();

        return [
            'success' => true,
            'status'  => $isHeld ? 'on_hold' : 'calculated',
            'summary' => [
                'employee_name'        => $employee->display_name,
                'payroll_month'        => $payrollMonth,
                'total_days'           => $totalDaysInMonth,
                'lop_days'             => $lopDays,
                'proration_rule'       => $prorationRule,
                'base_gross_earnings'  => round($grossEarnings, 2),
                'adhoc_earnings'       => round($adhocEarnings, 2),
                'retro_lop_reversals'  => round($totalRetroReversal, 2),
                'overtime_payout'      => round($totalOtPayout, 2),
                'overtime_hours'       => round($accumulatedOtHours, 2),
                'processed_ot_ids'     => $processedOtIds,
                'leave_encashment_payout' => round($totalEncashmentPayout, 2),
                'leave_encashment_days'   => round($totalEncashmentDays, 2),
                'processed_encash_ids'    => $processedEncashIds,
                'total_earnings'       => round($finalEarnings, 2),
                'base_deductions'      => round($grossDeductions, 2),
                'adhoc_deductions'     => round($adhocDeductions, 2),
                'attendance_penalties'      => round($penaltyDeductionAmount, 2),
                'attendance_penalty_days'   => round($penaltyDays, 2),
                'total_deductions'          => round($finalDeductions, 2),
                'net_payout'                => round($netPayout, 2),
            ],
            'items' => $computedSalaryItems,
        ];
    }
}
