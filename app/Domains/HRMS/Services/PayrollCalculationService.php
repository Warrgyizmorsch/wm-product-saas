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

        // Try to fetch corresponding active run to get exact user-defined start and end dates
        $run = \App\Domains\HRMS\Models\PayrollRun::where('payroll_month', $payrollMonth)
            ->where(function($q) use ($employee) {
                $q->whereNull('pay_group_id');
                if ($employee->pay_group_id) {
                    $q->orWhere('pay_group_id', $employee->pay_group_id);
                }
            })
            ->first();

        $startDate = $run && $run->start_date ? Carbon::parse($run->start_date) : $carbonMonth->copy()->startOfMonth();
        $endDate = $run && $run->end_date ? Carbon::parse($run->end_date) : $carbonMonth->copy()->endOfMonth();
        
        if ($run && $run->start_date && $run->end_date) {
            $totalDaysInMonth = $startDate->diffInDays($endDate) + 1;
        }

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
            // Count days in cycle excluding Sundays (0)
            $workingDays = 0;
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if ($date->dayOfWeek !== Carbon::SUNDAY) {
                    $workingDays++;
                }
            }
            $divisor = max(1, $workingDays);
        }

        // 4. Calculate LOP Days (Absences + Unpaid Leaves + Penalty Days combined in the run dates range)
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($a) => $a->date instanceof Carbon ? $a->date->format('Y-m-d') : Carbon::parse($a->date)->format('Y-m-d'));

        $holidays = \App\Domains\HRMS\Models\HolidayCalendar::where('tenant_id', $employee->tenant_id)
            ->whereBetween('holiday_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $leaves = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhere(function($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<=', $startDate->format('Y-m-d'))
                            ->where('end_date', '>=', $endDate->format('Y-m-d'));
                    });
            })
            ->get();

        $unpaidStatusLeaves = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'unpaid')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhere(function($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<=', $startDate->format('Y-m-d'))
                            ->where('end_date', '>=', $endDate->format('Y-m-d'));
                    });
            })
            ->get();

        $absenceDays = 0.0;
        $unpaidLeaveDays = 0.0;
        $today = Carbon::today();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            
            // Skip future dates to avoid counting upcoming days as absent
            if ($date->greaterThan($today)) {
                continue;
            }

            // 1. Check if there is an approved unpaid status leave on this date
            $activeUnpaidStatusLeave = $unpaidStatusLeaves->first(function($l) use ($dateStr) {
                $start = $l->start_date instanceof Carbon ? $l->start_date->format('Y-m-d') : Carbon::parse($l->start_date)->format('Y-m-d');
                $end = $l->end_date instanceof Carbon ? $l->end_date->format('Y-m-d') : Carbon::parse($l->end_date)->format('Y-m-d');
                return $dateStr >= $start && $dateStr <= $end;
            });

            if ($activeUnpaidStatusLeave) {
                $unpaidLeaveDays += 1.0;
                continue;
            }

            // 2. Check if there is an approved paid or unpaid leave request
            $activeLeave = $leaves->first(function($l) use ($dateStr) {
                $start = $l->start_date instanceof Carbon ? $l->start_date->format('Y-m-d') : Carbon::parse($l->start_date)->format('Y-m-d');
                $end = $l->end_date instanceof Carbon ? $l->end_date->format('Y-m-d') : Carbon::parse($l->end_date)->format('Y-m-d');
                return $dateStr >= $start && $dateStr <= $end;
            });

            if ($activeLeave) {
                if ($activeLeave->leaveType && $activeLeave->leaveType->type === 'unpaid') {
                    $unpaidLeaveDays += 1.0;
                }
                continue;
            }

            // 3. Check if it is a holiday
            $holiday = $holidays->first(function($h) use ($employee, $dateStr) {
                $hDate = $h->holiday_date instanceof Carbon ? $h->holiday_date->format('Y-m-d') : Carbon::parse($h->holiday_date)->format('Y-m-d');
                if ($hDate !== $dateStr) {
                    return false;
                }
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
                continue;
            }

            // 4. Check if it is a week off
            $dayOfWeek = $date->dayOfWeek;
            $isWeekOff = false;
            if (isset($employee->weekly_pattern) && is_array($employee->weekly_pattern)) {
                if (isset($employee->weekly_pattern[$dayOfWeek]) && $employee->weekly_pattern[$dayOfWeek] === 'off') {
                    $isWeekOff = true;
                } elseif ($dayOfWeek === 0 && (!isset($employee->weekly_pattern[$dayOfWeek]) || $employee->weekly_pattern[$dayOfWeek] === 'off')) {
                    $isWeekOff = true;
                }
            } else {
                if ($dayOfWeek === 0) {
                    $isWeekOff = true;
                }
            }

            if ($isWeekOff) {
                continue;
            }

            // 5. It's a working day. Check attendance status
            $att = $attendances->get($dateStr);
            if (!$att || $att->status === 'absent') {
                $absenceDays += 1.0;
            } elseif ($att->status === 'half_day') {
                $absenceDays += 0.5;
            }
        }

        $penaltyDaysCount = EmployeePenalty::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', '!=', 'excused')
            ->sum('penalty_amount');

        $lopDays = $absenceDays + $unpaidLeaveDays + $penaltyDaysCount;

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
            $monthlyCTC = $employee->current_salary / 12;

            if ($balancingItem) {
                $compCode = $balancingItem->component->code;
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
            } else {
                // If there is no manual balancing component, check if the total monthly base earnings equal monthly CTC
                $sumEarnings = 0.00;
                foreach ($computedSalaryItems as $code => $data) {
                    if ($data['type'] === 'earning') {
                        $sumEarnings += $data['base_monthly'];
                    }
                }

                if ($sumEarnings < $monthlyCTC) {
                    $remainder = $monthlyCTC - $sumEarnings;
                    
                    // If Special Allowance (SPL) is already in the structure, add the remainder to it
                    if (isset($computedSalaryItems['SPL'])) {
                        $computedSalaryItems['SPL']['base_monthly'] = round($computedSalaryItems['SPL']['base_monthly'] + $remainder, 2);
                        $computedSalaryItems['SPL']['calculated_value'] = round($computedSalaryItems['SPL']['calculated_value'] + $remainder, 2);
                    } else {
                        // Otherwise, fetch the SPL component and dynamically inject it to absorb the remainder
                        $splComponent = \App\Domains\HRMS\Models\SalaryComponent::where('code', 'SPL')->first();
                        if ($splComponent) {
                            $computedSalaryItems['SPL'] = [
                                'name'             => $splComponent->name,
                                'type'             => $splComponent->type,
                                'base_monthly'     => round($remainder, 2),
                                'calculated_value' => round($remainder, 2),
                                'deduction'        => 0.00,
                                'reversal'         => 0.00,
                            ];
                        }
                    }
                }
            }
        }

        // 6. Apply LOP deductions
        $totalLopDeduction = 0.00;
        $lopFactor = $divisor > 0 ? ($lopDays / $divisor) : 0;
        foreach ($computedSalaryItems as $code => &$item) {
            $itemPaidDays = $divisor;
            if ($item['type'] === 'earning') {
                if ($lopFactor > 0 && ($splicingRule === 'proportionate_gross' || in_array($code, ['BASIC', 'HRA']))) {
                    $itemLopDeduction = round($item['base_monthly'] * $lopFactor, 2);
                    $item['deduction'] = $itemLopDeduction;
                    // LOP is added as a standard deduction under Deductions table instead of being spliced from earnings directly.
                    $itemPaidDays = max(0.0, $divisor - $lopDays);
                    $totalLopDeduction += $itemLopDeduction;
                }
            }
            $item['paid_days'] = $itemPaidDays;
        }
        unset($item);

        if ($totalLopDeduction > 0) {
            $computedSalaryItems['LOP'] = [
                'name'             => 'Loss of Pay (' . $lopDays . ' days)',
                'type'             => 'deduction',
                'base_monthly'     => $totalLopDeduction,
                'calculated_value' => $totalLopDeduction,
                'deduction'        => 0.00,
                'reversal'         => 0.00,
            ];
        }

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
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
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
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
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

        // 9.9. Dynamic Statutory Calculations (PF & ESI)
        $enablePf = !isset($rules['enable_pf']) || (bool)$rules['enable_pf'];
        $restrictPfCeiling = !isset($rules['restrict_pf_ceiling']) || (bool)$rules['restrict_pf_ceiling'];
        $enableEsi = !isset($rules['enable_esi']) || (bool)$rules['enable_esi'];
        $restrictEsiThreshold = !isset($rules['restrict_esi_threshold']) || (bool)$rules['restrict_esi_threshold'];

        // Inject PF component if enabled but not in the structure items
        if ($enablePf && !isset($computedSalaryItems['PF'])) {
            $pfComponent = \App\Domains\HRMS\Models\SalaryComponent::where('code', 'PF')->first();
            if ($pfComponent) {
                $computedSalaryItems['PF'] = [
                    'name'             => $pfComponent->name,
                    'type'             => $pfComponent->type,
                    'base_monthly'     => 0.00,
                    'calculated_value' => 0.00,
                    'deduction'        => 0.00,
                    'reversal'         => 0.00,
                ];
            }
        }

        // Inject ESI component if enabled but not in the structure items
        if ($enableEsi && !isset($computedSalaryItems['ESI'])) {
            $esiComponent = \App\Domains\HRMS\Models\SalaryComponent::where('code', 'ESI')->first();
            if ($esiComponent) {
                $computedSalaryItems['ESI'] = [
                    'name'             => $esiComponent->name,
                    'type'             => $esiComponent->type,
                    'base_monthly'     => 0.00,
                    'calculated_value' => 0.00,
                    'deduction'        => 0.00,
                    'reversal'         => 0.00,
                ];
            }
        }

        // 9.9.1. Dynamic PF Calculation
        if (isset($computedSalaryItems['PF'])) {
            if ($enablePf) {
                // PF Wage Basis is standardly Basic + Dearness Allowance (DA)
                $earnedBasic = ($computedSalaryItems['BASIC']['calculated_value'] ?? 0.00) + ($computedSalaryItems['DA']['calculated_value'] ?? 0.00);
                $pfBasis = $restrictPfCeiling ? min($earnedBasic, 15000.00) : $earnedBasic;
                $pfDeduction = round($pfBasis * 0.12, 2);
                $computedSalaryItems['PF']['calculated_value'] = $pfDeduction;
                
                $baseBasic = ($computedSalaryItems['BASIC']['base_monthly'] ?? 0) + ($computedSalaryItems['DA']['base_monthly'] ?? 0);
                $computedSalaryItems['PF']['base_monthly'] = $restrictPfCeiling ? min($baseBasic, 15000.00) * 0.12 : $baseBasic * 0.12;
            } else {
                $computedSalaryItems['PF']['calculated_value'] = 0.00;
                $computedSalaryItems['PF']['base_monthly'] = 0.00;
            }
        }

        // 9.9.2. Dynamic ESI Calculation
        if (isset($computedSalaryItems['ESI'])) {
            if ($enableEsi) {
                // Under ESI regulations, eligibility is evaluated on Gross Salary EXCLUDING Overtime, Leave Encashment, and Retrospective Adjustments.
                $esiEligibleGross = 0.00;
                foreach ($computedSalaryItems as $code => $item) {
                    if ($item['type'] === 'earning' && !in_array($code, ['OVERTIME', 'LEAVE_ENCASHMENT']) && !str_starts_with($code, 'RETRO_REFUND')) {
                        $esiEligibleGross += $item['calculated_value'];
                    }
                }
                $esiEligibleGross += $adhocEarnings;

                // Once eligible, the ESI contribution is calculated on total gross INCLUDING Overtime.
                $esiContributionGross = $esiEligibleGross + $totalOtPayout;

                if (!$restrictEsiThreshold || ($esiEligibleGross <= 21000.00 && $esiEligibleGross > 0)) {
                    // ESI employee deduction must be rounded up to the next higher rupee (statutory ceil)
                    $esiDeduction = ceil($esiContributionGross * 0.0075);
                } else {
                    $esiDeduction = 0.00;
                }
                $computedSalaryItems['ESI']['calculated_value'] = $esiDeduction;

                $baseGross = 0.00;
                foreach ($computedSalaryItems as $code => $item) {
                    if ($item['type'] === 'earning' && !in_array($code, ['OVERTIME', 'LEAVE_ENCASHMENT']) && !str_starts_with($code, 'RETRO_REFUND')) {
                        $baseGross += $item['base_monthly'];
                    }
                }
                $computedSalaryItems['ESI']['base_monthly'] = (!$restrictEsiThreshold || $baseGross <= 21000.00) ? ceil($baseGross * 0.0075) : 0.00;
            } else {
                $computedSalaryItems['ESI']['calculated_value'] = 0.00;
                $computedSalaryItems['ESI']['base_monthly'] = 0.00;
            }
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
                'lop_deduction'        => round($totalLopDeduction, 2),
                'paid_days'            => max(0.0, $divisor - $lopDays),
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
