<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SectionCalculationService
{
    /**
     * Compute full calculation payload for a Section.
     */
    public function calculate(WorkCenterSection $section): array
    {
        $tenantId = (int) $section->tenant_id;

        // 1. Operating Hours & Shift Timeline Map
        $operatingHours = $this->calculateOperatingHours($section);
        $uniqueOperatingHours = (float) ($operatingHours['unique_clock_hours'] ?? 0.0);
        $shiftMap = $operatingHours['shift_map'] ?? [];
        $shiftsData = $operatingHours['shifts'] ?? [];
        $inactiveGaps = $operatingHours['inactive_gaps'] ?? [];

        // 2. Section Machines (Section-wide, running across unique operating hours)
        $machinesData = $this->calculateSectionMachines($section, $uniqueOperatingHours);

        // 3. Shift-Anchored Manual Batches
        $manualBatchesData = $this->calculateSectionManualBatches($section, $shiftMap);

        // 4. Labor Costing (Timeline Interval Slicing, HRMS Net Monthly CTC, External Pay Grades, Break Gaps)
        $laborData = $this->calculateSectionLabor($section, $shiftsData, $shiftMap, $uniqueOperatingHours, $inactiveGaps);

        // 5. Aggregate Primary Metrics
        $hourlyCapacity = round($machinesData['hourly_capacity'] + $manualBatchesData['hourly_manual_capacity'], 4);
        $dailyCapacity  = round($machinesData['daily_capacity'] + $manualBatchesData['daily_manual_capacity'], 4);

        $laborCostPerHour     = (float) ($laborData['average_hourly_labor_cost'] ?? 0.0);
        $totalDailyLaborCost  = (float) ($laborData['total_daily_labor_cost'] ?? 0.0);

        $machineCostPerHour     = (float) ($machinesData['hourly_operating_cost'] ?? 0.0);
        $totalDailyMachineCost  = (float) ($machinesData['daily_operating_cost'] ?? 0.0);

        $totalDailyOperatingCost = round($totalDailyLaborCost + $totalDailyMachineCost, 4);
        $totalOperatingCostPerHour = $uniqueOperatingHours > 0
            ? round($totalDailyOperatingCost / $uniqueOperatingHours, 4)
            : 0.0;

        // 6. Detailed JSON Audit Trail
        $calculationDetails = [
            'operating_hours' => [
                'unique_clock_hours'    => $uniqueOperatingHours,
                'unique_clock_minutes'  => $operatingHours['unique_clock_minutes'] ?? 0,
                'operational_window'    => $operatingHours['operational_window'] ?? 'None',
                'overlap_hours'         => $operatingHours['overlap_hours'] ?? 0.0,
                'has_overlapping_shifts'=> $operatingHours['has_overlapping_shifts'] ?? false,
                'has_gaps'              => $operatingHours['has_gaps'] ?? false,
                'inactive_gaps'         => $inactiveGaps,
                'shifts'                => $shiftsData,
            ],
            'machines'                  => $machinesData['machines'],
            'inactive_machines'         => $machinesData['inactive_machines'],
            'manual_batches'            => $manualBatchesData['manual_batches'],
            'inactive_manual_batches'   => $manualBatchesData['inactive_manual_batches'],
            'timeline_intervals'        => $laborData['timeline_intervals'],
            'internal_employees'        => $laborData['internal_employees'],
            'inactive_internal_employees' => $laborData['inactive_internal_employees'],
            'external_allocations'      => $laborData['external_allocations'],
            'inactive_external_allocations' => $laborData['inactive_external_allocations'],
            'unutilized_idle_hours'     => $laborData['unutilized_idle_hours'],
            'break_overlaps'            => $laborData['break_overlaps'],
            'totals' => [
                'section_unique_operating_hours' => $uniqueOperatingHours,
                'hourly_machine_capacity'        => $machinesData['hourly_capacity'],
                'daily_machine_capacity'         => $machinesData['daily_capacity'],
                'hourly_manual_capacity'         => $manualBatchesData['hourly_manual_capacity'],
                'daily_manual_capacity'          => $manualBatchesData['daily_manual_capacity'],
                'total_hourly_capacity'          => $hourlyCapacity,
                'total_daily_capacity'           => $dailyCapacity,
                'hourly_machine_expense'         => $machineCostPerHour,
                'total_daily_machine_expense'    => $totalDailyMachineCost,
                'total_daily_internal_labor_cost'=> $laborData['total_daily_internal_cost'] ?? 0.0,
                'total_daily_external_labor_cost'=> $laborData['total_daily_external_cost'] ?? 0.0,
                'total_daily_labor_cost'         => $totalDailyLaborCost,
                'average_hourly_labor_cost'      => $laborCostPerHour,
                'peak_hourly_labor_cost'         => $laborData['peak_hourly_labor_cost'] ?? 0.0,
                'total_daily_operating_cost'     => $totalDailyOperatingCost,
                'total_operating_cost_per_hour'  => $totalOperatingCostPerHour,
            ],
        ];

        return [
            'capacity_per_hour'             => $hourlyCapacity,
            'daily_capacity'                => $dailyCapacity,
            'labor_cost_per_hour'           => $laborCostPerHour,
            'total_daily_labor_cost'        => $totalDailyLaborCost,
            'machine_cost_per_hour'         => $machineCostPerHour,
            'total_daily_machine_cost'      => $totalDailyMachineCost,
            'total_daily_operating_cost'    => $totalDailyOperatingCost,
            'total_operating_cost_per_hour' => $totalOperatingCostPerHour,
            'unique_operating_hours'        => $uniqueOperatingHours,
            'calculation_details'           => $calculationDetails,
            'calculated_at'                 => now()->toIso8601String(),

            // Legacy backward-compatibility sub-keys
            'capacity' => [
                'capacity_per_hour'    => $hourlyCapacity,
                'total_capacity'       => $hourlyCapacity,
                'daily_capacity'       => $dailyCapacity,
                'capacity_unit'        => 'units/hr',
                'machine_capacity_sum' => $machinesData['hourly_capacity'],
                'machines'             => $machinesData['machines'],
                'manual_capacity'      => $manualBatchesData['hourly_manual_capacity'],
                'manual_batches'       => $manualBatchesData['manual_batches'],
            ],
            'labor_cost' => [
                'labor_cost_per_hour'        => $laborCostPerHour,
                'total_scheduled_labor_cost' => $totalDailyLaborCost,
                'total_daily_labor_cost'     => $totalDailyLaborCost,
                'unique_operating_hours'     => $uniqueOperatingHours,
                'has_overlapping_shifts'     => $operatingHours['has_overlapping_shifts'] ?? false,
                'shifts'                     => $shiftsData,
                'windows'                    => $laborData['timeline_intervals'],
                'timeline_windows'           => $laborData['timeline_intervals'],
            ],
        ];
    }

    /**
     * Calculate Section Operating Hours using clock interval union algorithm across 1440 minutes.
     */
    public function calculateOperatingHours(WorkCenterSection $section): array
    {
        $tenantId = (int) $section->tenant_id;

        $shifts = $section->relationLoaded('shifts')
            ? $section->shifts->where('tenant_id', $tenantId)->filter(fn($s) => (bool) ($s->active ?? true))
            : $section->shifts()->where('tenant_id', $tenantId)->where('production_shifts.active', true)->get();

        if ($shifts->isEmpty()) {
            return [
                'unique_clock_hours'     => 0.0,
                'unique_clock_minutes'   => 0,
                'operational_window'     => 'None',
                'overlap_hours'          => 0.0,
                'has_overlapping_shifts' => false,
                'has_gaps'               => false,
                'shifts'                 => [],
                'shift_map'              => [],
                'inactive_gaps'          => [],
            ];
        }

        $shiftData = [];
        $shiftMap  = [];

        foreach ($shifts as $shift) {
            $startMin = $this->parseTimeToMinutes($shift->start_time ?? '00:00');
            $endMin   = $this->parseTimeToMinutes($shift->end_time ?? '00:00');

            $isOvernight = ($endMin <= $startMin);
            $durationMin = $isOvernight ? (1440 - $startMin + $endMin) : ($endMin - $startMin);
            if ($durationMin === 0) {
                $durationMin = 1440;
            }

            $breakMin = max(0, (int) ($shift->break_minutes ?? 0));
            $effectiveMin = max(0, $durationMin - $breakMin);
            if ($effectiveMin === 0) {
                $effectiveMin = $durationMin;
            }

            $effectiveHours = round($effectiveMin / 60, 4);
            $durationHours  = round($durationMin / 60, 4);

            $item = [
                'id'               => $shift->id,
                'code'             => $shift->code,
                'name'             => $shift->name,
                'start_time'       => substr((string) $shift->start_time, 0, 5),
                'end_time'         => substr((string) $shift->end_time, 0, 5),
                'start_minute'     => $startMin,
                'end_minute'       => $endMin,
                'is_overnight'     => $isOvernight,
                'duration_minutes' => $durationMin,
                'duration_hours'   => $durationHours,
                'break_minutes'    => $breakMin,
                'break_hours'      => round($breakMin / 60, 4),
                'effective_minutes'=> $effectiveMin,
                'effective_hours'  => $effectiveHours,
            ];

            $shiftData[]          = $item;
            $shiftMap[$shift->id] = $item;
        }

        // Build 1440-minute map
        $minuteActiveShifts = array_fill(0, 1440, []);
        $covered            = array_fill(0, 1440, false);
        $overlapMinutes     = 0;

        foreach ($shiftData as $s) {
            $sMin   = $s['start_minute'];
            $eMin   = $s['end_minute'];
            $isOver = $s['is_overnight'];

            for ($m = 0; $m < 1440; $m++) {
                $isActive = $isOver
                    ? ($m >= $sMin || $m < $eMin)
                    : ($m >= $sMin && $m < $eMin);

                if ($isActive) {
                    $minuteActiveShifts[$m][] = $s['id'];
                    $covered[$m]              = true;
                }
            }
        }

        for ($m = 0; $m < 1440; $m++) {
            if (count($minuteActiveShifts[$m]) > 1) {
                $overlapMinutes++;
            }
        }

        $totalCoveredMinutes = count(array_filter($covered));
        $uniqueOperatingHours = round($totalCoveredMinutes / 60, 4);
        $overlapHours = round($overlapMinutes / 60, 4);
        $hasOverlap = $overlapMinutes > 0;

        // Window determination and inactive gaps
        $earliestStart = min(array_column($shiftData, 'start_minute'));
        $hasGaps       = ($totalCoveredMinutes > 0 && $totalCoveredMinutes < 1440);
        $inactiveGaps  = [];

        if ($hasGaps) {
            // Find transition point T0 where section begins operating after a gap
            $t0 = $earliestStart;
            for ($i = 0; $i < 1440; $i++) {
                $m     = ($earliestStart + $i) % 1440;
                $prevM = ($m - 1 + 1440) % 1440;
                if ($covered[$m] && !$covered[$prevM]) {
                    $t0 = $m;
                    break;
                }
            }

            // Detect inactive gaps between operating windows
            $inGap = false;
            $gapStart = null;
            for ($offset = 0; $offset < 1440; $offset++) {
                $m = ($t0 + $offset) % 1440;
                if (!$covered[$m] && !$inGap) {
                    $inGap = true;
                    $gapStart = $t0 + $offset;
                } elseif ($covered[$m] && $inGap) {
                    $inGap = false;
                    $gapEnd = $t0 + $offset;
                    $gapDurMin = $gapEnd - $gapStart;
                    $gapStartClock = $gapStart % 1440;
                    $gapEndClock   = $gapEnd % 1440;

                    $inactiveGaps[] = [
                        'start_time'     => sprintf('%02d:%02d', (int) floor($gapStartClock / 60), $gapStartClock % 60),
                        'end_time'       => sprintf('%02d:%02d', (int) floor($gapEndClock / 60), $gapEndClock % 60),
                        'window'         => sprintf('%02d:%02d - %02d:%02d', (int) floor($gapStartClock / 60), $gapStartClock % 60, (int) floor($gapEndClock / 60), $gapEndClock % 60),
                        'duration_minutes' => $gapDurMin,
                        'duration_hours' => round($gapDurMin / 60, 4),
                    ];
                }
            }
        }

        // Operational window label
        $earliestTime = sprintf('%02d:%02d', (int) floor($earliestStart / 60), $earliestStart % 60);
        $operationalWindow = $shifts->count() === 1
            ? "{$shiftData[0]['start_time']} - {$shiftData[0]['end_time']}"
            : "{$uniqueOperatingHours} hrs operational window ({$totalCoveredMinutes} mins)";

        return [
            'unique_clock_hours'     => $uniqueOperatingHours,
            'unique_clock_minutes'   => $totalCoveredMinutes,
            'operational_window'     => $operationalWindow,
            'overlap_hours'          => $overlapHours,
            'has_overlapping_shifts' => $hasOverlap,
            'has_gaps'               => !empty($inactiveGaps),
            'shifts'                 => $shiftData,
            'shift_map'              => $shiftMap,
            'inactive_gaps'          => $inactiveGaps,
            'minute_active_shifts'   => $minuteActiveShifts,
            'covered'                => $covered,
        ];
    }

    /**
     * Calculate Section-wide Machinery capacity and operating expense.
     * Machines operate across all unique operating hours of the section.
     */
    public function calculateSectionMachines(WorkCenterSection $section, float $uniqueOperatingHours): array
    {
        $tenantId = (int) $section->tenant_id;

        $machines = $section->relationLoaded('machines')
            ? $section->machines->where('tenant_id', $tenantId)
            : $section->machines()->where('tenant_id', $tenantId)->get();

        $activeMachines   = [];
        $inactiveMachines = [];

        $totalHourlyCapacity      = 0.0;
        $totalDailyCapacity       = 0.0;
        $totalHourlyOperatingCost = 0.0;
        $totalDailyOperatingCost  = 0.0;

        foreach ($machines as $machine) {
            $status = strtolower((string) ($machine->status ?? ''));
            $isActive = ($status === Machine::STATUS_ACTIVE || $status === 'active');

            $capHr = (float) ($machine->capacity ?? $machine->capacity_per_hour ?? 0.0);

            // Calculate or retrieve operating cost per hour
            $opCostHr = (float) ($machine->operating_cost_per_hour ?? 0.0);
            if ($opCostHr <= 0.0 && in_array($machine->energy_source, ['electricity', 'petrol', 'diesel'], true)) {
                $consumption = (float) ($machine->consumption_per_hour ?? 0.0);
                $rate = (float) ($machine->energy_rate_per_unit ?? 0.0);
                $opCostHr = round($consumption * $rate, 4);
            }

            $dailyCap  = round($capHr * $uniqueOperatingHours, 4);
            $dailyCost = round($opCostHr * $uniqueOperatingHours, 4);

            $item = [
                'id'                      => $machine->id,
                'code'                    => $machine->code,
                'name'                    => $machine->name,
                'status'                  => $machine->status,
                'energy_source'           => $machine->energy_source ?? 'electricity',
                'consumption_per_hour'    => (float) ($machine->consumption_per_hour ?? 0.0),
                'energy_rate_per_unit'    => (float) ($machine->energy_rate_per_unit ?? 0.0),
                'operating_cost_per_hour' => round($opCostHr, 4),
                'cap_hr'                  => round($capHr, 4),
                'section_operating_hrs'   => $uniqueOperatingHours,
                'daily_cap'               => $dailyCap,
                'daily_cost'              => $dailyCost,
            ];

            if ($isActive) {
                $totalHourlyCapacity      += $capHr;
                $totalDailyCapacity       += $dailyCap;
                $totalHourlyOperatingCost += $opCostHr;
                $totalDailyOperatingCost  += $dailyCost;
                $activeMachines[]          = $item;
            } else {
                $item['daily_cap']  = 0.0;
                $item['daily_cost'] = 0.0;
                $inactiveMachines[] = $item;
            }
        }

        return [
            'hourly_capacity'       => round($totalHourlyCapacity, 4),
            'daily_capacity'        => round($totalDailyCapacity, 4),
            'hourly_operating_cost' => round($totalHourlyOperatingCost, 4),
            'daily_operating_cost'  => round($totalDailyOperatingCost, 4),
            'machines'              => $activeMachines,
            'inactive_machines'     => $inactiveMachines,
        ];
    }

    /**
     * Calculate Shift-Anchored Manual Production Batches.
     */
    public function calculateSectionManualBatches(WorkCenterSection $section, array $shiftMap): array
    {
        $tenantId = (int) $section->tenant_id;

        $batches = $section->relationLoaded('manualProductions')
            ? $section->manualProductions->where('tenant_id', $tenantId)
            : $section->manualProductions()
                ->where('tenant_id', $tenantId)
                ->with(['shift', 'employees.employee', 'externalWorkerAllocations.payGrade'])
                ->get();

        $activeBatches   = [];
        $inactiveBatches = [];

        $totalHourlyCapacity = 0.0;
        $totalDailyCapacity  = 0.0;

        foreach ($batches as $batch) {
            $isActive = (bool) ($batch->active ?? true);
            $capHr    = (float) ($batch->capacity_per_hour ?? 0.0);

            $shiftId  = $batch->shift_id;
            $effHours = (float) ($shiftMap[$shiftId]['effective_hours'] ?? $shiftMap[$shiftId]['duration_hours'] ?? 0.0);
            $dailyCap = round($capHr * $effHours, 4);

            $empIds   = $batch->relationLoaded('employees')
                ? $batch->employees->pluck('work_center_employee_id')->all()
                : $batch->employees()->pluck('work_center_employee_id')->all();

            $allocIds = $batch->relationLoaded('externalWorkerAllocations')
                ? $batch->externalWorkerAllocations->pluck('id')->all()
                : $batch->externalWorkerAllocations()->pluck('id')->all();

            $item = [
                'id'                   => $batch->id,
                'batch_name'           => $batch->batch_name,
                'shift_id'             => $shiftId,
                'shift_name'           => $shiftMap[$shiftId]['name'] ?? ($batch->shift ? $batch->shift->name : 'N/A'),
                'cap_hr'               => round($capHr, 4),
                'eff_hrs'              => $effHours,
                'daily_cap'            => $dailyCap,
                'internal_employee_ids'=> $empIds,
                'external_allocation_ids'=> $allocIds,
                'active'               => $isActive,
            ];

            if ($isActive) {
                $totalHourlyCapacity += $capHr;
                $totalDailyCapacity  += $dailyCap;
                $activeBatches[]      = $item;
            } else {
                $item['daily_cap'] = 0.0;
                $inactiveBatches[] = $item;
            }
        }

        return [
            'hourly_manual_capacity' => round($totalHourlyCapacity, 4),
            'daily_manual_capacity'  => round($totalDailyCapacity, 4),
            'manual_batches'         => $activeBatches,
            'inactive_manual_batches'=> $inactiveBatches,
        ];
    }

    /**
     * Calculate Section Labor Cost using Timeline Interval Slicing.
     * Incorporates Internal Employees (HRMS Net CTC/30) and External Worker Allocations.
     */
    public function calculateSectionLabor(
        WorkCenterSection $section,
        array $shiftsData,
        array $shiftMap,
        float $uniqueOperatingHours,
        array $inactiveGaps = []
    ): array {
        $tenantId = (int) $section->tenant_id;

        // ── 1. Internal Employees assigned to Section ─────────────────────────────
        $assignments = $section->relationLoaded('employeeAssignments')
            ? $section->employeeAssignments->where('tenant_id', $tenantId)
            : $section->employeeAssignments()
                ->where('tenant_id', $tenantId)
                ->with(['employee', 'manualProductions'])
                ->get();

        $activeEmployees   = [];
        $inactiveEmployees = [];
        $unutilizedIdleHours = [];
        $breakOverlaps = [];

        $totalDailyInternalCost = 0.0;

        foreach ($assignments as $assignment) {
            $emp = $assignment->employee;
            if (!$emp) {
                continue;
            }

            $isActive = (bool) ($assignment->active ?? true) && (bool) ($emp->status ?? true);

            // Determine Shift:
            // 1. Check if assigned to manual batch
            $manualBatchShiftId = null;
            if ($assignment->relationLoaded('manualProductions') && $assignment->manualProductions->isNotEmpty()) {
                $manualBatchShiftId = $assignment->manualProductions->first()->shift_id;
            }
            $shiftId = $manualBatchShiftId ?: ($emp->shift_id ?: (!empty($shiftsData) ? $shiftsData[0]['id'] : null));

            // HRMS Net Monthly Salary
            $monthlySalary = (float) ($emp->current_salary ?? 0.0);
            $hasSalaryWarning = false;
            $salaryWarning = null;
            if ($monthlySalary <= 0.0) {
                $hasSalaryWarning = true;
                $salaryWarning = '⚠️ Missing HRMS Salary: defaults to $0.00';
            }

            $dailySalary = round($monthlySalary / 30, 4);

            $shiftEffHours = (float) ($shiftMap[$shiftId]['effective_hours'] ?? $shiftMap[$shiftId]['duration_hours'] ?? 8.0);
            if ($shiftEffHours <= 0) {
                $shiftEffHours = 8.0;
            }

            $hourlyRate = round($dailySalary / $shiftEffHours, 4);
            $dailyCost  = $dailySalary;

            $item = [
                'id'                      => $assignment->id,
                'assignment_id'           => $assignment->id,
                'employee_id'             => $emp->id,
                'employee_code'           => $emp->employee_id,
                'name'                    => $emp->full_name,
                'shift_id'                => $shiftId,
                'shift_name'              => $shiftMap[$shiftId]['name'] ?? 'N/A',
                'hrms_net_monthly_salary' => $monthlySalary,
                'daily_rate_formula'      => "{$monthlySalary} / 30",
                'daily_salary'            => $dailySalary,
                'shift_hours'             => $shiftEffHours,
                'hourly_rate_formula'     => "{$dailySalary} / {$shiftEffHours}",
                'hourly_rate'             => $hourlyRate,
                'daily_cost'              => $dailyCost,
                'has_salary_warning'      => $hasSalaryWarning,
                'salary_warning'          => $salaryWarning,
                'active'                  => $isActive,
            ];

            if ($isActive) {
                $totalDailyInternalCost += $dailyCost;
                $activeEmployees[]       = $item;

                // Break & Inactive Gap Mismatch Checking (Section 10 of Plan)
                $empBreakMinutes = (int) ($shiftMap[$shiftId]['break_minutes'] ?? 0);
                if (!empty($inactiveGaps)) {
                    foreach ($inactiveGaps as $gap) {
                        $gapMinutes = (int) ($gap['duration_minutes'] ?? 0);
                        if ($gapMinutes > $empBreakMinutes) {
                            $idleMin = $gapMinutes - $empBreakMinutes;
                            $idleHours = round($idleMin / 60, 4);
                            $unutilizedIdleHours[] = [
                                'employee_id'    => $emp->id,
                                'employee_name'  => $emp->full_name,
                                'window'         => $gap['window'],
                                'gap_duration'   => $gapMinutes,
                                'break_duration' => $empBreakMinutes,
                                'duration_hours' => $idleHours,
                                'idle_cost'      => round($idleHours * $hourlyRate, 4),
                                'message'        => "Employee on clock while section is idle during gap ({$idleHours} hrs idle)",
                            ];
                        }
                    }
                }

                if ($empBreakMinutes > 0 && empty($inactiveGaps)) {
                    $breakOverlaps[] = [
                        'employee_id'    => $emp->id,
                        'employee_name'  => $emp->full_name,
                        'shift_name'     => $shiftMap[$shiftId]['name'] ?? 'N/A',
                        'break_minutes'  => $empBreakMinutes,
                        'message'        => "Employee has {$empBreakMinutes}m break during continuous section run",
                    ];
                }
            } else {
                $item['daily_cost']  = 0.0;
                $item['hourly_rate'] = 0.0;
                $inactiveEmployees[] = $item;
            }
        }

        // ── 2. External Worker Allocations ─────────────────────────────────────────
        $allocations = $section->relationLoaded('externalWorkerAllocations')
            ? $section->externalWorkerAllocations->where('tenant_id', $tenantId)
            : $section->externalWorkerAllocations()
                ->where('tenant_id', $tenantId)
                ->with('payGrade')
                ->get();

        $activeAllocations   = [];
        $inactiveAllocations = [];
        $totalDailyExternalCost = 0.0;

        foreach ($allocations as $alloc) {
            $isActive = (bool) ($alloc->active ?? true);
            $headcount = (int) ($alloc->number_of_external_workers ?? 1);
            $payGrade = $alloc->payGrade;
            $payGradeRate = (float) ($payGrade->hourly_rate ?? 0.0);
            $hourlyBurnRate = round($headcount * $payGradeRate, 4);

            $shiftId = $alloc->shift_id;
            $shiftEffHours = (float) ($shiftMap[$shiftId]['effective_hours'] ?? $shiftMap[$shiftId]['duration_hours'] ?? 0.0);
            $dailyCost = round($hourlyBurnRate * $shiftEffHours, 4);

            $item = [
                'id'                   => $alloc->id,
                'allocation_id'        => $alloc->id,
                'shift_id'             => $shiftId,
                'shift_name'           => $shiftMap[$shiftId]['name'] ?? 'N/A',
                'pay_grade_id'         => $alloc->pay_grade_id,
                'pay_grade_name'       => $payGrade ? ($payGrade->name ?? $payGrade->grade_name ?? 'N/A') : 'N/A',
                'headcount'            => $headcount,
                'pay_grade_rate'       => $payGradeRate,
                'hourly_burn_rate'     => $hourlyBurnRate,
                'shift_hours'          => $shiftEffHours,
                'daily_cost'           => $dailyCost,
                'manual_production_id' => $alloc->manual_production_id,
                'active'               => $isActive,
            ];

            if ($isActive) {
                $totalDailyExternalCost += $dailyCost;
                $activeAllocations[]     = $item;
            } else {
                $item['daily_cost']        = 0.0;
                $item['hourly_burn_rate']  = 0.0;
                $inactiveAllocations[]     = $item;
            }
        }

        // ── 3. 1440-Minute Timeline Interval Slicing ───────────────────────────────
        if (empty($shiftsData) || (empty($activeEmployees) && empty($activeAllocations))) {
            $totalDailyLabor = round($totalDailyInternalCost + $totalDailyExternalCost, 4);
            $avgHourlyRate   = $uniqueOperatingHours > 0 ? round($totalDailyLabor / $uniqueOperatingHours, 4) : 0.0;

            return [
                'total_daily_internal_cost'    => round($totalDailyInternalCost, 4),
                'total_daily_external_cost'    => round($totalDailyExternalCost, 4),
                'total_daily_labor_cost'       => $totalDailyLabor,
                'average_hourly_labor_cost'    => $avgHourlyRate,
                'peak_hourly_labor_cost'       => 0.0,
                'timeline_intervals'           => [],
                'internal_employees'           => $activeEmployees,
                'inactive_internal_employees'  => $inactiveEmployees,
                'external_allocations'         => $activeAllocations,
                'inactive_external_allocations'=> $inactiveAllocations,
                'unutilized_idle_hours'        => $unutilizedIdleHours,
                'break_overlaps'               => $breakOverlaps,
            ];
        }

        // Precompute minute burn rates and active resource rosters
        $minuteActiveShifts = array_fill(0, 1440, []);
        $minuteEmployees    = array_fill(0, 1440, []);
        $minuteAllocations  = array_fill(0, 1440, []);
        $coveredMinutes     = array_fill(0, 1440, false);

        foreach ($shiftsData as $s) {
            $sId    = $s['id'];
            $sMin   = $s['start_minute'];
            $eMin   = $s['end_minute'];
            $isOver = $s['is_overnight'];

            for ($m = 0; $m < 1440; $m++) {
                $isActive = $isOver ? ($m >= $sMin || $m < $eMin) : ($m >= $sMin && $m < $eMin);
                if ($isActive) {
                    $minuteActiveShifts[$m][] = $sId;
                    $coveredMinutes[$m]       = true;
                }
            }
        }

        foreach ($activeEmployees as $emp) {
            $sId = $emp['shift_id'];
            if (!$sId || !isset($shiftMap[$sId])) {
                continue;
            }
            $s = $shiftMap[$sId];
            $sMin = $s['start_minute'];
            $eMin = $s['end_minute'];
            $isOver = $s['is_overnight'];

            for ($m = 0; $m < 1440; $m++) {
                $isActive = $isOver ? ($m >= $sMin || $m < $eMin) : ($m >= $sMin && $m < $eMin);
                if ($isActive) {
                    $minuteEmployees[$m][] = $emp;
                }
            }
        }

        foreach ($activeAllocations as $alloc) {
            $sId = $alloc['shift_id'];
            if (!$sId || !isset($shiftMap[$sId])) {
                continue;
            }
            $s = $shiftMap[$sId];
            $sMin = $s['start_minute'];
            $eMin = $s['end_minute'];
            $isOver = $s['is_overnight'];

            for ($m = 0; $m < 1440; $m++) {
                $isActive = $isOver ? ($m >= $sMin || $m < $eMin) : ($m >= $sMin && $m < $eMin);
                if ($isActive) {
                    $minuteAllocations[$m][] = $alloc;
                }
            }
        }

        // Anchor T0 to earliest shift start
        $earliestStart = min(array_column($shiftsData, 'start_minute'));
        $totalCoveredCount = count(array_filter($coveredMinutes));

        $t0 = $earliestStart;
        if ($totalCoveredCount < 1440) {
            for ($i = 0; $i < 1440; $i++) {
                $m     = ($earliestStart + $i) % 1440;
                $prevM = ($m - 1 + 1440) % 1440;
                if ($coveredMinutes[$m] && !$coveredMinutes[$prevM]) {
                    $t0 = $m;
                    break;
                }
            }
        }

        // Slice intervals across 1440 minutes
        $intervals = [];
        $currentStart = null;
        $currentActiveShifts = null;

        for ($offset = 0; $offset <= 1440; $offset++) {
            $T = $t0 + $offset;
            $m = $T % 1440;

            $activeShifts = ($offset < 1440) ? $minuteActiveShifts[$m] : [];
            sort($activeShifts);
            $activeKey = implode(',', $activeShifts);
            $currentKey = ($currentActiveShifts !== null) ? implode(',', $currentActiveShifts) : '';

            if ($currentStart !== null && $activeKey !== $currentKey) {
                if (!empty($currentActiveShifts)) {
                    $durMin = $T - $currentStart;
                    $durHours = round($durMin / 60, 4);

                    $startClockMin = $currentStart % 1440;
                    $endClockMin   = $T % 1440;
                    $startTimeStr  = sprintf('%02d:%02d', (int) floor($startClockMin / 60), $startClockMin % 60);
                    $endTimeStr    = sprintf('%02d:%02d', (int) floor($endClockMin / 60), $endClockMin % 60);

                    // Resolve resources covering this interval
                    $midM = ($currentStart + (int) floor($durMin / 2)) % 1440;
                    $intEmps   = $minuteEmployees[$midM] ?? [];
                    $intAllocs = $minuteAllocations[$midM] ?? [];

                    $hourlyBurn = 0.0;
                    foreach ($intEmps as $ie) {
                        $hourlyBurn += (float) $ie['hourly_rate'];
                    }
                    foreach ($intAllocs as $ia) {
                        $hourlyBurn += (float) $ia['hourly_burn_rate'];
                    }
                    $hourlyBurn = round($hourlyBurn, 4);
                    $intervalCost = round($hourlyBurn * $durHours, 4);

                    $intervals[] = [
                        'window'                        => "{$startTimeStr} - {$endTimeStr}",
                        'start_time'                    => $startTimeStr,
                        'end_time'                      => $endTimeStr,
                        'duration_hours'                => $durHours,
                        'active_shift_ids'              => $currentActiveShifts,
                        'active_internal_employee_ids'  => array_column($intEmps, 'employee_id'),
                        'active_external_allocation_ids'=> array_column($intAllocs, 'allocation_id'),
                        'hourly_burn_rate'              => $hourlyBurn,
                        'interval_total_cost'           => $intervalCost,
                        'is_overlapping'                => count($currentActiveShifts) > 1,
                    ];
                }
                $currentStart = null;
                $currentActiveShifts = null;
            }

            if (!empty($activeShifts) && $currentStart === null) {
                $currentStart = $T;
                $currentActiveShifts = $activeShifts;
            }
        }

        // Determine Peak Overlap
        $maxBurn = 0.0;
        foreach ($intervals as $int) {
            if ($int['hourly_burn_rate'] > $maxBurn) {
                $maxBurn = $int['hourly_burn_rate'];
            }
        }
        foreach ($intervals as &$int) {
            $int['is_peak_overlap'] = ($int['hourly_burn_rate'] === $maxBurn && count($int['active_shift_ids']) > 1);
        }
        unset($int);

        $totalDailyLabor = round($totalDailyInternalCost + $totalDailyExternalCost, 4);
        $averageHourlyLabor = $uniqueOperatingHours > 0
            ? round($totalDailyLabor / $uniqueOperatingHours, 4)
            : 0.0;

        return [
            'total_daily_internal_cost'    => round($totalDailyInternalCost, 4),
            'total_daily_external_cost'    => round($totalDailyExternalCost, 4),
            'total_daily_labor_cost'       => $totalDailyLabor,
            'average_hourly_labor_cost'    => $averageHourlyLabor,
            'peak_hourly_labor_cost'       => $maxBurn,
            'timeline_intervals'           => $intervals,
            'internal_employees'           => $activeEmployees,
            'inactive_internal_employees'  => $inactiveEmployees,
            'external_allocations'         => $activeAllocations,
            'inactive_external_allocations'=> $inactiveAllocations,
            'unutilized_idle_hours'        => $unutilizedIdleHours,
            'break_overlaps'               => $breakOverlaps,
        ];
    }

    /**
     * Calculate and persist Section calculation snapshot into immutable history table.
     */
    public function calculateAndSave(
        WorkCenterSection $section,
        string $triggerType = 'manual',
        ?string $batchId = null,
        ?int $wcCalculationId = null
    ): WorkCenterSectionCalculation {
        $calc = $this->calculate($section);
        $batchId = $batchId ?: (string) Str::uuid();

        $record = WorkCenterSectionCalculation::create([
            'tenant_id'                     => (int) $section->tenant_id,
            'work_center_id'                => (int) $section->work_center_id,
            'section_id'                    => (int) $section->id,
            'work_center_calculation_id'    => $wcCalculationId,
            'batch_id'                      => $batchId,
            'trigger_type'                  => $triggerType,
            'capacity_per_hour'             => $calc['capacity_per_hour'],
            'daily_capacity'                => $calc['daily_capacity'],
            'labor_cost_per_hour'           => $calc['labor_cost_per_hour'],
            'total_daily_labor_cost'        => $calc['total_daily_labor_cost'],
            'machine_cost_per_hour'         => $calc['machine_cost_per_hour'],
            'total_daily_machine_cost'      => $calc['total_daily_machine_cost'],
            'total_daily_operating_cost'    => $calc['total_daily_operating_cost'],
            'total_operating_cost_per_hour' => $calc['total_operating_cost_per_hour'],
            'unique_operating_hours'        => $calc['unique_operating_hours'],
            'calculation_details'           => $calc['calculation_details'],
        ]);

        // Record immutable audit activity log
        app(WorkCenterActivityLogger::class)->logSectionCalculationCompleted($record);

        return $record;
    }

    /**
     * Legacy helper to calculate capacity alone.
     */
    public function calculateCapacity(WorkCenterSection $section): array
    {
        $calc = $this->calculate($section);
        return $calc['capacity'];
    }

    /**
     * Legacy helper to calculate labor cost alone.
     */
    public function calculateLaborCost(WorkCenterSection $section): array
    {
        $calc = $this->calculate($section);
        return $calc['labor_cost'];
    }

    /**
     * Helper to parse HH:MM to 0-1439 minute index.
     */
    private function parseTimeToMinutes(string $timeStr): int
    {
        $parts = explode(':', trim($timeStr));
        $h     = (int) ($parts[0] ?? 0);
        $m     = (int) ($parts[1] ?? 0);

        return ($h * 60 + $m) % 1440;
    }
}
