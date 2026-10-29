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

        // ── 1. Operating Hours & Shift Timeline Map ───────────────────────────
        $operatingHours = $this->calculateOperatingHours($section);
        $uniqueOperatingHours = (float) ($operatingHours['unique_clock_hours'] ?? 0.0);
        $shiftMap = $operatingHours['shift_map'] ?? [];
        $shiftsData = $operatingHours['shifts'] ?? [];
        $inactiveGaps = $operatingHours['inactive_gaps'] ?? [];
        $hasZeroShifts = empty($shiftsData) || $uniqueOperatingHours <= 0;

        // ── 2. Machinery (Runs across Section Unique Operating Hours) ──────────
        $machinesData = $this->calculateSectionMachines($section, $uniqueOperatingHours);

        // ── 3. Shift-Anchored Manual Batches ──────────────────────────────────
        $manualBatchesData = $this->calculateSectionManualBatches($section, $shiftMap, $uniqueOperatingHours);

        // ── 4. Labor Costing (Timeline Interval Slicing, CTC/30, Full Clock Divisor) ──
        $laborData = $this->calculateSectionLabor(
            $section,
            $shiftsData,
            $shiftMap,
            $uniqueOperatingHours,
            $machinesData['hourly_operating_cost'],
            $machinesData['hourly_capacity'],
            $manualBatchesData['shift_manual_capacities'],
            $inactiveGaps
        );

        // ── 5. Primary Metric Aggregations ────────────────────────────────────
        $dailyMachineCap = (float) $machinesData['daily_capacity'];
        $dailyManualCap  = (float) $manualBatchesData['daily_manual_capacity'];
        $totalDailyCap   = round($dailyMachineCap + $dailyManualCap, 4);

        $totalHourlyCap  = $uniqueOperatingHours > 0
            ? round($totalDailyCap / $uniqueOperatingHours, 4)
            : 0.0;

        $peakHourlyManualCap = (float) ($laborData['peak_interval_manual_capacity'] ?? $manualBatchesData['peak_hourly_manual_capacity']);
        $peakTotalHourlyCap  = round($machinesData['hourly_capacity'] + $peakHourlyManualCap, 4);

        $totalDailyInternalLabor = (float) ($laborData['total_daily_internal_cost'] ?? 0.0);
        $totalDailyExternalLabor = (float) ($laborData['total_daily_external_cost'] ?? 0.0);
        $totalDailyLaborCost     = round($totalDailyInternalLabor + $totalDailyExternalLabor, 4);

        $avgInternalHourlyRate = $uniqueOperatingHours > 0
            ? round($totalDailyInternalLabor / $uniqueOperatingHours, 4)
            : 0.0;
        $avgExternalHourlyRate = $uniqueOperatingHours > 0
            ? round($totalDailyExternalLabor / $uniqueOperatingHours, 4)
            : 0.0;
        $totalAvgHourlyLaborRate = $uniqueOperatingHours > 0
            ? round($totalDailyLaborCost / $uniqueOperatingHours, 4)
            : 0.0;

        $peakHourlyLaborRate = (float) ($laborData['peak_hourly_labor_cost'] ?? 0.0);

        $hourlyMachineExpense     = (float) ($machinesData['hourly_operating_cost'] ?? 0.0);
        $totalDailyMachineExpense = (float) ($machinesData['daily_operating_cost'] ?? 0.0);

        $totalDailyOperatingCost = round($totalDailyLaborCost + $totalDailyMachineExpense, 4);
        $totalHourlyOperatingCost = $uniqueOperatingHours > 0
            ? round($totalDailyOperatingCost / $uniqueOperatingHours, 4)
            : 0.0;

        $peakTotalHourlyOperatingCost = round($hourlyMachineExpense + $peakHourlyLaborRate, 4);

        // Unit Cost Calculations
        $costPerUnit = $totalDailyCap > 0
            ? round($totalDailyOperatingCost / $totalDailyCap, 4)
            : 0.0;
        $machineCostPerUnit = $totalDailyCap > 0
            ? round($totalDailyMachineExpense / $totalDailyCap, 4)
            : 0.0;
        $internalLaborCostPerUnit = $totalDailyCap > 0
            ? round($totalDailyInternalLabor / $totalDailyCap, 4)
            : 0.0;
        $externalLaborCostPerUnit = $totalDailyCap > 0
            ? round($totalDailyExternalLabor / $totalDailyCap, 4)
            : 0.0;
        $totalLaborCostPerUnit = $totalDailyCap > 0
            ? round($totalDailyLaborCost / $totalDailyCap, 4)
            : 0.0;

        // ── 6. Comprehensive 52-Metric JSON Audit Payload ─────────────────────
        $calculationDetails = [
            // Category 1: Operating Timeline & Shift Window Metrics
            'operating_timeline' => [
                'shifts_count'                    => count($shiftsData),
                'total_shift_clock_hours'         => round((float) array_sum(array_column($shiftsData, 'duration_hours')), 4),
                'section_unique_operating_hours'  => $uniqueOperatingHours,
                'shift_overlap_hours'             => (float) ($operatingHours['overlap_hours'] ?? 0.0),
                'has_overlapping_shifts'          => (bool) ($operatingHours['has_overlapping_shifts'] ?? false),
                'operational_window_start'        => $operatingHours['window_start'] ?? 'None',
                'operational_window_end'          => $operatingHours['window_end'] ?? 'None',
                'has_inactive_gaps'               => (bool) ($operatingHours['has_gaps'] ?? false),
                'inactive_gap_hours'              => round((float) array_sum(array_column($inactiveGaps, 'duration_hours')), 4),
                'advisory_notes'                  => $hasZeroShifts
                    ? 'Section has no active shifts assigned. Operating hours, capacities, and costs are zero.'
                    : null,
                'shifts'                          => $shiftsData,
                'inactive_gaps'                   => $inactiveGaps,
            ],

            // Category 2: Machinery Capacity & Opex Metrics
            'machinery' => [
                'active_machines_count'                 => count($machinesData['machines']),
                'inactive_machines_count'               => count($machinesData['inactive_machines']),
                'hourly_machine_capacity'               => $machinesData['hourly_capacity'],
                'daily_machine_capacity'                => $dailyMachineCap,
                'sum_machine_consumption_per_hour'      => $machinesData['sum_consumption_per_hour'],
                'calculated_machine_base_cost_per_hour' => $machinesData['sum_base_energy_cost_per_hour'],
                'total_machine_additional_cost_per_hour'=> $machinesData['sum_additional_cost_per_hour'],
                'total_machine_deductibles_per_hour'    => $machinesData['sum_deductibles_per_hour'],
                'hourly_machine_expense'                => $hourlyMachineExpense,
                'total_daily_machine_expense'           => $totalDailyMachineExpense,
                'machine_cost_per_unit'                 => $machineCostPerUnit,
                'machines'                              => $machinesData['machines'],
            ],

            // Category 3: Manual Batch Capacity Metrics
            'manual_batches' => [
                'active_manual_batches_count'   => count($manualBatchesData['manual_batches']),
                'inactive_manual_batches_count' => count($manualBatchesData['inactive_manual_batches']),
                'daily_manual_capacity'         => $dailyManualCap,
                'hourly_manual_capacity'        => (float) $manualBatchesData['hourly_manual_capacity'],
                'peak_hourly_manual_capacity'   => $peakHourlyManualCap,
                'shift_manual_capacities'       => $manualBatchesData['shift_manual_capacities'],
                'batches'                       => $manualBatchesData['manual_batches'],
            ],

            // Category 4: Internal Employee Labor Metrics (Salaried Staff)
            'internal_labor' => [
                'internal_employees_count'        => count($laborData['internal_employees']),
                'total_internal_monthly_salary'   => (float) ($laborData['total_internal_monthly_salary'] ?? 0.0),
                'total_internal_daily_salary'     => (float) ($laborData['total_internal_daily_salary'] ?? 0.0),
                'total_daily_internal_labor_cost' => $totalDailyInternalLabor,
                'average_internal_hourly_rate'    => $avgInternalHourlyRate,
                'shift_internal_hourly_rates'     => $laborData['shift_internal_hourly_rates'] ?? [],
                'peak_internal_hourly_rate'       => (float) ($laborData['peak_internal_hourly_rate'] ?? 0.0),
                'internal_labor_cost_per_unit'    => $internalLaborCostPerUnit,
                'employees'                       => $laborData['internal_employees'],
            ],

            // Category 5: External Contractor Labor Metrics (Hourly Staff)
            'external_labor' => [
                'external_allocations_count'      => count($laborData['external_allocations']),
                'total_external_headcount'        => (int) ($laborData['total_external_headcount'] ?? 0),
                'total_daily_external_labor_cost' => $totalDailyExternalLabor,
                'average_external_hourly_rate'    => $avgExternalHourlyRate,
                'shift_external_hourly_rates'     => $laborData['shift_external_hourly_rates'] ?? [],
                'peak_external_hourly_rate'       => (float) ($laborData['peak_external_hourly_rate'] ?? 0.0),
                'external_labor_cost_per_unit'    => $externalLaborCostPerUnit,
                'allocations'                     => $laborData['external_allocations'],
            ],

            // Category 6: Combined Labor Metrics
            'combined_labor' => [
                'total_daily_labor_cost'         => $totalDailyLaborCost,
                'total_average_hourly_labor_rate'=> $totalAvgHourlyLaborRate,
                'peak_hourly_labor_rate'         => $peakHourlyLaborRate,
                'total_labor_cost_per_unit'      => $totalLaborCostPerUnit,
            ],

            // Category 7: Total Section Financial & Production Summary
            'financial_summary' => [
                'total_daily_capacity'           => $totalDailyCap,
                'total_hourly_capacity'          => $totalHourlyCap,
                'peak_total_hourly_capacity'     => $peakTotalHourlyCap,
                'total_daily_operating_cost'     => $totalDailyOperatingCost,
                'total_hourly_operating_cost'    => $totalHourlyOperatingCost,
                'peak_total_hourly_operating_cost'=> $peakTotalHourlyOperatingCost,
                'cost_per_unit'                  => $costPerUnit,
            ],

            // Detailed Breakdown Arrays & Timelines
            'timeline_intervals'            => $laborData['timeline_intervals'],
            'inactive_machines'             => $machinesData['inactive_machines'],
            'inactive_manual_batches'       => $manualBatchesData['inactive_manual_batches'],
            'inactive_internal_employees'   => $laborData['inactive_internal_employees'],
            'inactive_external_allocations' => $laborData['inactive_external_allocations'],
            'unutilized_idle_hours'         => $laborData['unutilized_idle_hours'],
            'break_overlaps'                => $laborData['break_overlaps'],

            // Legacy backward-compatibility 'totals' sub-key
            'totals' => [
                'section_unique_operating_hours' => $uniqueOperatingHours,
                'hourly_machine_capacity'        => $machinesData['hourly_capacity'],
                'daily_machine_capacity'         => $dailyMachineCap,
                'hourly_manual_capacity'         => $manualBatchesData['hourly_manual_capacity'],
                'daily_manual_capacity'          => $dailyManualCap,
                'total_hourly_capacity'          => $totalHourlyCap,
                'total_daily_capacity'           => $totalDailyCap,
                'peak_total_hourly_capacity'     => $peakTotalHourlyCap,
                'hourly_machine_expense'         => $hourlyMachineExpense,
                'total_daily_machine_expense'    => $totalDailyMachineExpense,
                'total_daily_internal_labor_cost'=> $totalDailyInternalLabor,
                'total_daily_external_labor_cost'=> $totalDailyExternalLabor,
                'total_daily_labor_cost'         => $totalDailyLaborCost,
                'average_hourly_labor_cost'      => $totalAvgHourlyLaborRate,
                'peak_hourly_labor_cost'         => $peakHourlyLaborRate,
                'total_daily_operating_cost'     => $totalDailyOperatingCost,
                'total_operating_cost_per_hour'  => $totalHourlyOperatingCost,
                'cost_per_unit'                  => $costPerUnit,
            ],
        ];

        return [
            'capacity_per_hour'               => $totalHourlyCap,
            'daily_capacity'                  => $totalDailyCap,
            'hourly_machine_capacity'         => (float) $machinesData['hourly_capacity'],
            'daily_machine_capacity'          => (float) $dailyMachineCap,
            'hourly_manual_capacity'          => (float) $manualBatchesData['hourly_manual_capacity'],
            'daily_manual_capacity'           => (float) $dailyManualCap,
            'labor_cost_per_hour'             => $totalAvgHourlyLaborRate,
            'total_daily_labor_cost'          => $totalDailyLaborCost,
            'internal_labor_cost_per_hour'    => (float) $avgInternalHourlyRate,
            'total_daily_internal_labor_cost' => (float) $totalDailyInternalLabor,
            'external_labor_cost_per_hour'    => (float) $avgExternalHourlyRate,
            'total_daily_external_labor_cost' => (float) $totalDailyExternalLabor,
            'machine_cost_per_hour'           => $hourlyMachineExpense,
            'total_daily_machine_cost'        => $totalDailyMachineExpense,
            'total_daily_operating_cost'      => $totalDailyOperatingCost,
            'total_operating_cost_per_hour'   => $totalHourlyOperatingCost,
            'unique_operating_hours'          => $uniqueOperatingHours,
            'calculation_details'             => $calculationDetails,
            'calculated_at'                   => now()->toIso8601String(),

            // Backward-compatibility sub-arrays for legacy consumers
            'capacity' => [
                'capacity_per_hour'    => $totalHourlyCap,
                'total_capacity'       => $totalHourlyCap,
                'daily_capacity'       => $totalDailyCap,
                'capacity_unit'        => 'units/hr',
                'machine_capacity_sum' => $machinesData['hourly_capacity'],
                'machines'             => $machinesData['machines'],
                'manual_capacity'      => $manualBatchesData['hourly_manual_capacity'],
                'manual_batches'       => $manualBatchesData['manual_batches'],
            ],
            'labor_cost' => [
                'labor_cost_per_hour'        => $totalAvgHourlyLaborRate,
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
                'window_start'           => 'None',
                'window_end'             => 'None',
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

            $currentGapStart = null;
            for ($offset = 0; $offset <= 1440; $offset++) {
                $m = ($t0 + $offset) % 1440;
                $isMinuteCovered = $covered[$m];

                if (!$isMinuteCovered && $currentGapStart === null) {
                    $currentGapStart = $t0 + $offset;
                } elseif ($isMinuteCovered && $currentGapStart !== null) {
                    $gapDurMin = ($t0 + $offset) - $currentGapStart;
                    if ($gapDurMin > 0) {
                        $gStartClock = $currentGapStart % 1440;
                        $gEndClock   = ($t0 + $offset) % 1440;
                        $inactiveGaps[] = [
                            'start_time'       => sprintf('%02d:%02d', (int) floor($gStartClock / 60), $gStartClock % 60),
                            'end_time'         => sprintf('%02d:%02d', (int) floor($gEndClock / 60), $gEndClock % 60),
                            'duration_minutes' => $gapDurMin,
                            'duration_hours'   => round($gapDurMin / 60, 4),
                            'window'           => sprintf('%02d:%02d - %02d:%02d', (int) floor($gStartClock / 60), $gStartClock % 60, (int) floor($gEndClock / 60), $gEndClock % 60),
                        ];
                    }
                    $currentGapStart = null;
                }
            }
        }

        $windowStart = sprintf('%02d:%02d', (int) floor($earliestStart / 60), $earliestStart % 60);
        $windowEndMin = ($earliestStart + $totalCoveredMinutes) % 1440;
        $windowEnd   = sprintf('%02d:%02d', (int) floor($windowEndMin / 60), $windowEndMin % 60);

        return [
            'unique_clock_hours'     => $uniqueOperatingHours,
            'unique_clock_minutes'   => $totalCoveredMinutes,
            'operational_window'     => "{$windowStart} - {$windowEnd}",
            'window_start'           => $windowStart,
            'window_end'             => $windowEnd,
            'overlap_hours'          => $overlapHours,
            'has_overlapping_shifts' => $hasOverlap,
            'has_gaps'               => $hasGaps,
            'shifts'                 => $shiftData,
            'shift_map'              => $shiftMap,
            'inactive_gaps'          => $inactiveGaps,
            'minute_active_shifts'   => $minuteActiveShifts,
            'covered'                => $covered,
        ];
    }

    /**
     * Calculate Section-wide Machinery capacity and operating expense.
     * Incorporates energy consumption, base energy cost, and entered cost reconciliation (additional_cost vs deductibles).
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

        $sumConsumption           = 0.0;
        $sumBaseEnergyCost        = 0.0;
        $sumAdditionalCost        = 0.0;
        $sumDeductibles           = 0.0;

        foreach ($machines as $machine) {
            $status = strtolower((string) ($machine->status ?? ''));
            $isActive = ($status === Machine::STATUS_ACTIVE || $status === 'active');

            $capHr = (float) ($machine->capacity ?? $machine->capacity_per_hour ?? 0.0);
            $consumption = (float) ($machine->consumption_per_hour ?? 0.0);
            $rate = (float) ($machine->energy_rate_per_unit ?? 0.0);

            // 1. Calculate Base Energy Cost
            $baseEnergyCost = round($consumption * $rate, 4);

            // 2. Entered Operating Cost
            $enteredCost = (float) ($machine->operating_cost_per_hour ?? 0.0);

            // 3. Reconcile Discrepancy
            $additionalCost = 0.0;
            $deductibles    = 0.0;
            $explanation    = '';
            $finalOpCostHr  = 0.0;

            if ($enteredCost > 0.0 && $baseEnergyCost > 0.0) {
                if ($enteredCost > $baseEnergyCost) {
                    $additionalCost = round($enteredCost - $baseEnergyCost, 4);
                    $finalOpCostHr  = $enteredCost;
                    $explanation    = 'Includes $' . number_format($additionalCost, 2) . '/hr additional cost for consumables, lubricants, or tooling wear.';
                } elseif ($enteredCost < $baseEnergyCost) {
                    $deductibles   = round($baseEnergyCost - $enteredCost, 4);
                    $finalOpCostHr = $enteredCost;
                    $explanation   = 'Includes $' . number_format($deductibles, 2) . '/hr deductibles for power subsidies, solar credits, or discounts.';
                } else {
                    $finalOpCostHr = $enteredCost;
                    $explanation   = 'Matches calculated base energy consumption rate ($' . number_format($finalOpCostHr, 2) . '/hr).';
                }
            } elseif ($enteredCost > 0.0) {
                $finalOpCostHr = $enteredCost;
                $explanation   = 'Flat operating cost specified ($' . number_format($finalOpCostHr, 2) . '/hr).';
            } elseif ($baseEnergyCost > 0.0) {
                $finalOpCostHr = $baseEnergyCost;
                $explanation   = 'Calculated from energy consumption (' . number_format($consumption, 2) . ' units @ $' . number_format($rate, 2) . '/unit = $' . number_format($finalOpCostHr, 2) . '/hr).';
            } else {
                $finalOpCostHr = 0.0;
                $explanation   = 'No energy consumption or operating cost entered ($0.00/hr).';
            }

            $dailyCap  = round($capHr * $uniqueOperatingHours, 4);
            $dailyCost = round($finalOpCostHr * $uniqueOperatingHours, 4);

            $capFormula = number_format($capHr, 2) . ' units/hr x ' . format_hours($uniqueOperatingHours) . ' section operating hrs = ' . number_format($dailyCap, 2) . ' units/day';
            $costFormula = '$' . number_format($finalOpCostHr, 2) . '/hr x ' . format_hours($uniqueOperatingHours) . ' section operating hrs = $' . number_format($dailyCost, 2) . '/day';

            $item = [
                'id'                      => $machine->id,
                'code'                    => $machine->code,
                'name'                    => $machine->name,
                'status'                  => $machine->status,
                'energy_source'           => $machine->energy_source ?? 'electricity',
                'consumption_per_hour'    => $consumption,
                'energy_rate_per_unit'    => $rate,
                'calculated_base_cost_hr' => $baseEnergyCost,
                'entered_cost_per_hour'   => $enteredCost,
                'additional_cost_per_hour'=> $additionalCost,
                'deductibles_per_hour'    => $deductibles,
                'operating_cost_per_hour' => round($finalOpCostHr, 4),
                'cost_explanation'        => $explanation,
                'cap_hr'                  => round($capHr, 4),
                'capacity_formula'        => $capFormula,
                'cost_formula'            => $costFormula,
                'section_operating_hrs'   => $uniqueOperatingHours,
                'daily_cap'               => $dailyCap,
                'daily_cost'              => $dailyCost,
                'active'                  => $isActive,
            ];

            if ($isActive) {
                $totalHourlyCapacity      += $capHr;
                $totalDailyCapacity       += $dailyCap;
                $totalHourlyOperatingCost += $finalOpCostHr;
                $totalDailyOperatingCost  += $dailyCost;
                $sumConsumption           += $consumption;
                $sumBaseEnergyCost        += $baseEnergyCost;
                $sumAdditionalCost        += $additionalCost;
                $sumDeductibles           += $deductibles;
                $activeMachines[]          = $item;
            } else {
                $item['daily_cap']  = 0.0;
                $item['daily_cost'] = 0.0;
                $inactiveMachines[] = $item;
            }
        }

        return [
            'hourly_capacity'                  => round($totalHourlyCapacity, 4),
            'daily_capacity'                   => round($totalDailyCapacity, 4),
            'hourly_operating_cost'            => round($totalHourlyOperatingCost, 4),
            'daily_operating_cost'             => round($totalDailyOperatingCost, 4),
            'sum_consumption_per_hour'         => round($sumConsumption, 4),
            'sum_base_energy_cost_per_hour'    => round($sumBaseEnergyCost, 4),
            'sum_additional_cost_per_hour'     => round($sumAdditionalCost, 4),
            'sum_deductibles_per_hour'         => round($sumDeductibles, 4),
            'machines'                         => $activeMachines,
            'inactive_machines'                => $inactiveMachines,
        ];
    }

    /**
     * Calculate Shift-Anchored Manual Production Batches.
     * Each batch only produces during its assigned shift's effective hours.
     * Hourly capacity is the dynamic weighted average = total daily manual capacity / section operating hours.
     */
    public function calculateSectionManualBatches(WorkCenterSection $section, array $shiftMap, float $uniqueOperatingHours): array
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

        $totalDailyCapacity = 0.0;
        $shiftManualCapacities = [];

        foreach ($batches as $batch) {
            $isActive = (bool) ($batch->active ?? true);
            $capHr    = (float) ($batch->capacity_per_hour ?? 0.0);

            $shiftId  = $batch->shift_id;
            $effHours = (float) ($shiftMap[$shiftId]['effective_hours'] ?? $shiftMap[$shiftId]['duration_hours'] ?? 0.0);
            $dailyCap = round($capHr * $effHours, 4);

            $empNames = [];
            if ($batch->relationLoaded('employees')) {
                foreach ($batch->employees as $be) {
                    if ($be->employee) {
                        $empNames[] = $be->employee->full_name;
                    }
                }
            } else {
                $empNames = $batch->employees()->with('employee')->get()->pluck('employee.full_name')->filter()->values()->all();
            }

            $allocSummary = [];
            if ($batch->relationLoaded('externalWorkerAllocations')) {
                foreach ($batch->externalWorkerAllocations as $alloc) {
                    $pgName = $alloc->payGrade ? ($alloc->payGrade->name ?? 'Contractor') : 'Contractor';
                    $allocSummary[] = "{$alloc->number_of_external_workers}x {$pgName}";
                }
            } else {
                $allocSummary = $batch->externalWorkerAllocations()->with('payGrade')->get()->map(function ($alloc) {
                    $pgName = $alloc->payGrade ? ($alloc->payGrade->name ?? 'Contractor') : 'Contractor';
                    return "{$alloc->number_of_external_workers}x {$pgName}";
                })->all();
            }

            $shiftName = $shiftMap[$shiftId]['name'] ?? ($batch->shift ? $batch->shift->name : "Shift #{$shiftId}");
            $formula = "Batch capacity (" . number_format($capHr, 2) . " units/hr) x Shift effective hours (" . format_hours($effHours) . ") = " . number_format($dailyCap, 2) . " units/day";

            $item = [
                'id'                      => $batch->id,
                'batch_name'              => $batch->batch_name,
                'shift_id'                => $shiftId,
                'shift_name'              => $shiftName,
                'cap_hr'                  => round($capHr, 4),
                'eff_hrs'                 => $effHours,
                'daily_cap'               => $dailyCap,
                'formula'                 => $formula,
                'team_members'            => $empNames,
                'contractor_allocations'  => $allocSummary,
                'active'                  => $isActive,
            ];

            if ($isActive) {
                $totalDailyCapacity += $dailyCap;
                $activeBatches[]     = $item;

                if (!isset($shiftManualCapacities[$shiftId])) {
                    $shiftManualCapacities[$shiftId] = [
                        'shift_id'     => $shiftId,
                        'shift_name'   => $shiftName,
                        'manual_speed' => 0.0,
                    ];
                }
                $shiftManualCapacities[$shiftId]['manual_speed'] += $capHr;
            } else {
                $item['daily_cap'] = 0.0;
                $inactiveBatches[] = $item;
            }
        }

        // Weighted average hourly capacity
        $hourlyManualCap = $uniqueOperatingHours > 0
            ? round($totalDailyCapacity / $uniqueOperatingHours, 4)
            : 0.0;

        // Peak manual speed across shifts
        $peakManualSpeed = !empty($shiftManualCapacities)
            ? max(array_column($shiftManualCapacities, 'manual_speed'))
            : 0.0;

        return [
            'daily_manual_capacity'   => round($totalDailyCapacity, 4),
            'hourly_manual_capacity'  => $hourlyManualCap,
            'peak_hourly_manual_capacity' => round($peakManualSpeed, 4),
            'shift_manual_capacities' => array_values($shiftManualCapacities),
            'manual_batches'          => $activeBatches,
            'inactive_manual_batches' => $inactiveBatches,
        ];
    }

    /**
     * Calculate Section Labor Cost using Timeline Interval Slicing.
     * Incorporates Internal Employees (HRMS CTC / 30 / Shift Clock Duration) and External Workers (Paid Full Shift).
     */
    public function calculateSectionLabor(
        WorkCenterSection $section,
        array $shiftsData,
        array $shiftMap,
        float $uniqueOperatingHours,
        float $hourlyMachineExpense,
        float $hourlyMachineCapacity,
        array $shiftManualCapacitiesList,
        array $inactiveGaps = []
    ): array {
        $tenantId = (int) $section->tenant_id;

        // Map shift manual speeds by shift ID for interval overlays
        $shiftManualSpeedMap = [];
        foreach ($shiftManualCapacitiesList as $smc) {
            $shiftManualSpeedMap[$smc['shift_id']] = (float) $smc['manual_speed'];
        }

        // ── 1. Internal Employees Assigned to Section ─────────────────────────────
        $assignments = $section->relationLoaded('employeeAssignments')
            ? $section->employeeAssignments->where('tenant_id', $tenantId)
            : $section->employeeAssignments()
                ->where('tenant_id', $tenantId)
                ->with(['employee', 'manualProductions'])
                ->get();

        $activeEmployees        = [];
        $inactiveEmployees      = [];
        $unutilizedIdleHours    = [];
        $breakOverlaps          = [];

        $totalMonthlySalary     = 0.0;
        $totalDailyInternalCost = 0.0;
        $shiftInternalRates     = [];

        foreach ($assignments as $assignment) {
            $emp = $assignment->employee;
            if (!$emp) {
                continue;
            }

            $isActive = (bool) ($assignment->active ?? true) && (bool) ($emp->status ?? true);

            // Determine Shift: check manual batch assignment first, then employee default, then first section shift
            $manualBatchShiftId = null;
            if ($assignment->relationLoaded('manualProductions') && $assignment->manualProductions->isNotEmpty()) {
                $manualBatchShiftId = $assignment->manualProductions->first()->shift_id;
            }
            $shiftId = $manualBatchShiftId ?: ($emp->shift_id ?: (!empty($shiftsData) ? $shiftsData[0]['id'] : null));

            // HRMS Net Monthly CTC
            $monthlySalary = (float) ($emp->current_salary ?? 0.0);
            $hasSalaryWarning = false;
            $salaryWarning = null;
            if ($monthlySalary <= 0.0) {
                $hasSalaryWarning = true;
                $salaryWarning = 'Missing HRMS salary: defaults to $0.00';
            }

            // Daily Salary = Net Monthly CTC / 30 days
            $dailySalary = round($monthlySalary / 30, 4);

            // Hourly Salary Rate Divisor = Full Shift Clock Duration (including paid breaks)
            $isOutsideSection = false;
            if ($shiftId && isset($shiftMap[$shiftId])) {
                $shiftName = $shiftMap[$shiftId]['name'];
                $shiftClockHours = (float) ($shiftMap[$shiftId]['duration_hours'] ?? 8.0);
            } elseif ($shiftId) {
                // Shift assigned to employee is outside section's configured operating shifts
                $resolvedShift = $emp->relationLoaded('shift') && $emp->shift
                    ? $emp->shift
                    : \App\Domains\Production\Models\ProductionShift::find($shiftId);

                if ($resolvedShift) {
                    $shiftName = $resolvedShift->name;
                    $st = \Carbon\Carbon::parse($resolvedShift->start_time);
                    $et = \Carbon\Carbon::parse($resolvedShift->end_time);
                    if ($et->lt($st)) {
                        $et->addDay();
                    }
                    $shiftClockHours = round($st->diffInMinutes($et) / 60, 2);
                    $isOutsideSection = true;
                } else {
                    $shiftName = !empty($shiftsData) ? $shiftsData[0]['name'] : 'N/A';
                    $shiftClockHours = !empty($shiftsData) ? (float) ($shiftsData[0]['duration_hours'] ?? 8.0) : 8.0;
                }
            } else {
                $shiftName = !empty($shiftsData) ? $shiftsData[0]['name'] : 'N/A';
                $shiftClockHours = !empty($shiftsData) ? (float) ($shiftsData[0]['duration_hours'] ?? 8.0) : 8.0;
            }

            if ($shiftClockHours <= 0) {
                $shiftClockHours = 8.0;
            }

            $hourlyRate = round($dailySalary / $shiftClockHours, 4);
            $dailyCost  = $dailySalary;

            $formula = "Monthly CTC ($" . number_format($monthlySalary, 2) . ") / 30 days = $" . number_format($dailySalary, 2) . "/day; $" . number_format($dailySalary, 2) . " / " . format_hours($shiftClockHours) . " shift clock hrs = $" . number_format($hourlyRate, 2) . "/hr";

            $item = [
                'id'                      => $assignment->id,
                'assignment_id'           => $assignment->id,
                'employee_id'             => $emp->id,
                'employee_code'           => $emp->employee_id,
                'name'                    => $emp->full_name,
                'employee_name'           => $emp->full_name,
                'shift_id'                => $shiftId,
                'shift_name'              => $shiftName,
                'is_outside_section_shifts' => $isOutsideSection,
                'hrms_net_monthly_salary' => $monthlySalary,
                'monthly_salary'          => $monthlySalary,
                'daily_salary'            => $dailySalary,
                'shift_clock_hours'       => $shiftClockHours,
                'shift_duration_hours'    => $shiftClockHours,
                'hourly_rate'             => $hourlyRate,
                'hourly_salary_rate'      => $hourlyRate,
                'daily_cost'              => $dailyCost,
                'formula'                 => $formula,
                'has_salary_warning'      => $hasSalaryWarning,
                'salary_warning'          => $salaryWarning,
                'active'                  => $isActive,
            ];

            if ($isActive) {
                $totalMonthlySalary     += $monthlySalary;
                $totalDailyInternalCost += $dailyCost;
                $activeEmployees[]       = $item;

                if (!isset($shiftInternalRates[$shiftId])) {
                    $shiftInternalRates[$shiftId] = [
                        'shift_id'   => $shiftId,
                        'shift_name' => $shiftName,
                        'hourly_burn'=> 0.0,
                    ];
                }
                $shiftInternalRates[$shiftId]['hourly_burn'] += $hourlyRate;

                // Gap and break mismatch monitoring
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
                        'shift_name'     => $shiftName,
                        'break_minutes'  => $empBreakMinutes,
                        'message'        => "Employee has {$empBreakMinutes}m paid break during continuous section run",
                    ];
                }
            } else {
                $item['daily_cost']  = 0.0;
                $item['hourly_rate'] = 0.0;
                $inactiveEmployees[] = $item;
            }
        }

        // ── 2. External Worker Allocations (Contractors) ───────────────────────────
        $allocations = $section->relationLoaded('externalWorkerAllocations')
            ? $section->externalWorkerAllocations->where('tenant_id', $tenantId)
            : $section->externalWorkerAllocations()
                ->where('tenant_id', $tenantId)
                ->with('payGrade')
                ->get();

        $activeAllocations      = [];
        $inactiveAllocations    = [];
        $totalDailyExternalCost = 0.0;
        $totalExternalHeadcount = 0;
        $shiftExternalRates     = [];

        foreach ($allocations as $alloc) {
            $isActive = (bool) ($alloc->active ?? true);
            $headcount = (int) ($alloc->number_of_external_workers ?? 1);
            $payGrade = $alloc->payGrade;
            $payGradeRate = (float) ($payGrade->hourly_rate ?? 0.0);
            $hourlyBurnRate = round($headcount * $payGradeRate, 4);

            $shiftId = $alloc->shift_id;
            // Contractors paid for full shift clock duration including lunch
            $shiftClockHours = (float) ($shiftMap[$shiftId]['duration_hours'] ?? 8.0);
            $dailyCost = round($hourlyBurnRate * $shiftClockHours, 4);

            $shiftName = $shiftMap[$shiftId]['name'] ?? ($alloc->shift ? $alloc->shift->name : "Shift #{$shiftId}");
            $pgName = $payGrade ? ($payGrade->name ?? $payGrade->grade_name ?? 'Contractor') : 'Contractor';
            $formula = "{$headcount} workers x $" . number_format($payGradeRate, 2) . "/hr x " . format_hours($shiftClockHours) . " shift clock hrs = $" . number_format($dailyCost, 2) . "/day";

            $item = [
                'id'                   => $alloc->id,
                'allocation_id'        => $alloc->id,
                'shift_id'             => $shiftId,
                'shift_name'           => $shiftName,
                'pay_grade_id'         => $alloc->pay_grade_id,
                'pay_grade_name'       => $pgName,
                'headcount'            => $headcount,
                'pay_grade_rate'       => $payGradeRate,
                'hourly_burn_rate'     => $hourlyBurnRate,
                'shift_clock_hours'    => $shiftClockHours,
                'shift_duration_hours' => $shiftClockHours,
                'daily_cost'           => $dailyCost,
                'formula'              => $formula,
                'manual_production_id' => $alloc->manual_production_id,
                'active'               => $isActive,
            ];

            if ($isActive) {
                $totalDailyExternalCost += $dailyCost;
                $totalExternalHeadcount += $headcount;
                $activeAllocations[]     = $item;

                if (!isset($shiftExternalRates[$shiftId])) {
                    $shiftExternalRates[$shiftId] = [
                        'shift_id'   => $shiftId,
                        'shift_name' => $shiftName,
                        'hourly_burn'=> 0.0,
                    ];
                }
                $shiftExternalRates[$shiftId]['hourly_burn'] += $hourlyBurnRate;
            } else {
                $item['daily_cost']        = 0.0;
                $item['hourly_burn_rate']  = 0.0;
                $inactiveAllocations[]     = $item;
            }
        }

        // ── 3. 1440-Minute Timeline Interval Slicing ───────────────────────────────
        // Only abort if the section has no active shifts configured.
        // Machine-only sections (with 0 employees/allocations) still generate valid shift intervals.
        if (empty($shiftsData)) {
            $totalDailyLabor = round($totalDailyInternalCost + $totalDailyExternalCost, 4);
            $avgHourlyRate   = $uniqueOperatingHours > 0 ? round($totalDailyLabor / $uniqueOperatingHours, 4) : 0.0;

            return [
                'total_internal_monthly_salary'=> round($totalMonthlySalary, 4),
                'total_internal_daily_salary'  => round($totalMonthlySalary / 30, 4),
                'total_daily_internal_cost'    => round($totalDailyInternalCost, 4),
                'total_external_headcount'     => $totalExternalHeadcount,
                'total_daily_external_cost'    => round($totalDailyExternalCost, 4),
                'total_daily_labor_cost'       => $totalDailyLabor,
                'average_hourly_labor_cost'    => $avgHourlyRate,
                'peak_hourly_labor_cost'       => 0.0,
                'peak_internal_hourly_rate'    => 0.0,
                'peak_external_hourly_rate'    => 0.0,
                'peak_interval_manual_capacity'=> 0.0,
                'shift_internal_hourly_rates'  => array_values($shiftInternalRates),
                'shift_external_hourly_rates'  => array_values($shiftExternalRates),
                'timeline_intervals'           => [],
                'internal_employees'           => $activeEmployees,
                'inactive_internal_employees'  => $inactiveEmployees,
                'external_allocations'         => $activeAllocations,
                'inactive_external_allocations'=> $inactiveAllocations,
                'unutilized_idle_hours'        => $unutilizedIdleHours,
                'break_overlaps'               => $breakOverlaps,
            ];
        }

        // Minute mapping for timeline intervals
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

                    $internalBurn = 0.0;
                    foreach ($intEmps as $ie) {
                        $internalBurn += (float) $ie['hourly_rate'];
                    }
                    $externalBurn = 0.0;
                    foreach ($intAllocs as $ia) {
                        $externalBurn += (float) $ia['hourly_burn_rate'];
                    }
                    $totalLaborBurn = round($internalBurn + $externalBurn, 4);
                    $intervalLaborCost = round($totalLaborBurn * $durHours, 4);

                    // Running manual capacity during this interval (sum of active shifts' manual speeds)
                    $intervalManualCap = 0.0;
                    foreach ($currentActiveShifts as $sId) {
                        $intervalManualCap += ($shiftManualSpeedMap[$sId] ?? 0.0);
                    }
                    $intervalTotalCap = round($hourlyMachineCapacity + $intervalManualCap, 4);
                    $intervalTotalOutput = round($intervalTotalCap * $durHours, 4);

                    // Total operating cost rate for interval
                    $intervalTotalCostRate = round($hourlyMachineExpense + $totalLaborBurn, 4);
                    $intervalTotalCost = round($intervalTotalCostRate * $durHours, 4);

                    $isOverlap = count($currentActiveShifts) > 1;
                    if ($totalLaborBurn > 0) {
                        $explanation = $isOverlap
                            ? "Shift Overlap: " . count($currentActiveShifts) . " shifts active concurrently. Labor burn rate surges to $" . number_format($totalLaborBurn, 2) . "/hr."
                            : "Regular Window: 1 shift active. Labor burn rate: $" . number_format($totalLaborBurn, 2) . "/hr.";
                    } else {
                        $explanation = $isOverlap
                            ? "Shift Overlap: " . count($currentActiveShifts) . " shifts active concurrently. Machine capacity: " . number_format($hourlyMachineCapacity, 1) . " u/h."
                            : "Regular Window: 1 shift active. Machine capacity: " . number_format($hourlyMachineCapacity, 1) . " u/h.";
                    }

                    $intervals[] = [
                        'window'                        => "{$startTimeStr} - {$endTimeStr}",
                        'start_time'                    => $startTimeStr,
                        'end_time'                      => $endTimeStr,
                        'duration_hours'                => $durHours,
                        'active_shift_ids'              => $currentActiveShifts,
                        'active_internal_employee_ids'  => array_column($intEmps, 'employee_id'),
                        'active_external_allocation_ids'=> array_column($intAllocs, 'allocation_id'),
                        'running_machine_capacity'      => $hourlyMachineCapacity,
                        'running_manual_capacity'       => $intervalManualCap,
                        'running_total_capacity'        => $intervalTotalCap,
                        'running_internal_labor_rate'   => round($internalBurn, 4),
                        'running_external_labor_rate'   => round($externalBurn, 4),
                        'running_total_labor_rate'      => $totalLaborBurn,
                        'running_machine_rate'          => $hourlyMachineExpense,
                        'running_total_cost_rate'       => $intervalTotalCostRate,
                        'window_total_output'           => $intervalTotalOutput,
                        'window_total_cost'             => $intervalTotalCost,
                        'interval_labor_cost'           => $intervalLaborCost,
                        'is_overlapping'                => $isOverlap,
                        'explanation'                   => $explanation,
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

        // Determine Peak Rates across Intervals
        $maxLaborBurn    = 0.0;
        $maxInternalBurn = 0.0;
        $maxExternalBurn = 0.0;
        $maxManualCap    = 0.0;

        foreach ($intervals as $int) {
            if ($int['running_total_labor_rate'] > $maxLaborBurn) {
                $maxLaborBurn = $int['running_total_labor_rate'];
            }
            if ($int['running_internal_labor_rate'] > $maxInternalBurn) {
                $maxInternalBurn = $int['running_internal_labor_rate'];
            }
            if ($int['running_external_labor_rate'] > $maxExternalBurn) {
                $maxExternalBurn = $int['running_external_labor_rate'];
            }
            if ($int['running_manual_capacity'] > $maxManualCap) {
                $maxManualCap = $int['running_manual_capacity'];
            }
        }

        foreach ($intervals as &$int) {
            $int['is_peak_overlap'] = ($int['running_total_labor_rate'] === $maxLaborBurn && $int['is_overlapping']);
        }
        unset($int);

        $totalDailyLabor = round($totalDailyInternalCost + $totalDailyExternalCost, 4);
        $averageHourlyLabor = $uniqueOperatingHours > 0
            ? round($totalDailyLabor / $uniqueOperatingHours, 4)
            : 0.0;

        return [
            'total_internal_monthly_salary'=> round($totalMonthlySalary, 4),
            'total_internal_daily_salary'  => round($totalMonthlySalary / 30, 4),
            'total_daily_internal_cost'    => round($totalDailyInternalCost, 4),
            'total_external_headcount'     => $totalExternalHeadcount,
            'total_daily_external_cost'    => round($totalDailyExternalCost, 4),
            'total_daily_labor_cost'       => $totalDailyLabor,
            'average_hourly_labor_cost'    => $averageHourlyLabor,
            'peak_hourly_labor_cost'       => $maxLaborBurn,
            'peak_internal_hourly_rate'    => $maxInternalBurn,
            'peak_external_hourly_rate'    => $maxExternalBurn,
            'peak_interval_manual_capacity'=> $maxManualCap,
            'shift_internal_hourly_rates'  => array_values($shiftInternalRates),
            'shift_external_hourly_rates'  => array_values($shiftExternalRates),
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
     * Note: work_center_id is omitted as it has been dropped from the table.
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
            'tenant_id'                       => (int) $section->tenant_id,
            'section_id'                      => (int) $section->id,
            'work_center_calculation_id'      => $wcCalculationId,
            'batch_id'                        => $batchId,
            'trigger_type'                    => $triggerType,
            'capacity_per_hour'               => $calc['capacity_per_hour'],
            'daily_capacity'                  => $calc['daily_capacity'],
            'hourly_machine_capacity'         => $calc['hourly_machine_capacity'],
            'daily_machine_capacity'          => $calc['daily_machine_capacity'],
            'hourly_manual_capacity'          => $calc['hourly_manual_capacity'],
            'daily_manual_capacity'           => $calc['daily_manual_capacity'],
            'labor_cost_per_hour'             => $calc['labor_cost_per_hour'],
            'total_daily_labor_cost'          => $calc['total_daily_labor_cost'],
            'internal_labor_cost_per_hour'    => $calc['internal_labor_cost_per_hour'],
            'total_daily_internal_labor_cost' => $calc['total_daily_internal_labor_cost'],
            'external_labor_cost_per_hour'    => $calc['external_labor_cost_per_hour'],
            'total_daily_external_labor_cost' => $calc['total_daily_external_labor_cost'],
            'machine_cost_per_hour'           => $calc['machine_cost_per_hour'],
            'total_daily_machine_cost'        => $calc['total_daily_machine_cost'],
            'total_daily_operating_cost'      => $calc['total_daily_operating_cost'],
            'total_operating_cost_per_hour'   => $calc['total_operating_cost_per_hour'],
            'unique_operating_hours'          => $calc['unique_operating_hours'],
            'calculation_details'             => $calc['calculation_details'],
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
    protected function parseTimeToMinutes(string $time): int
    {
        $parts = explode(':', trim($time));
        $h = isset($parts[0]) ? (int) $parts[0] : 0;
        $m = isset($parts[1]) ? (int) $parts[1] : 0;
        return ($h * 60 + $m) % 1440;
    }
}
