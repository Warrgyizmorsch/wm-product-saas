<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\WorkCenterSection;

class SectionCalculationService
{
    /**
     * Calculate Section Capacity Per Hour.
     *
     * Formula:
     * Section Capacity Per Hour = sum(capacities of all assigned machines)
     *                           + enabled manual-production capacity per hour.
     */
    public function calculateCapacity(WorkCenterSection $section): array
    {
        $tenantId = (int) $section->tenant_id;

        $machines = $section->relationLoaded('machines')
            ? $section->machines->where('tenant_id', $tenantId)
            : $section->machines()->where('tenant_id', $tenantId)->get();

        $machineCapacitySum = 0.0;
        $machineDetails = [];

        foreach ($machines as $machine) {
            $cap = (float) ($machine->capacity ?? 0.0);
            $machineCapacitySum += $cap;
            $machineDetails[] = [
                'id'       => $machine->id,
                'code'     => $machine->code,
                'name'     => $machine->name,
                'capacity' => round($cap, 4),
            ];
        }

        $supportsManual = (bool) $section->supports_manual_production;
        $manualCapacity = $supportsManual ? (float) ($section->manual_capacity_per_hour ?? 0.0) : 0.0;

        $totalCapacity = round($machineCapacitySum + $manualCapacity, 4);

        return [
            'capacity_per_hour'       => $totalCapacity,
            'total_capacity'          => $totalCapacity,
            'capacity_unit'           => 'units/hr',
            'unit'                    => 'units/hr',
            'machine_capacity_sum'    => round($machineCapacitySum, 4),
            'machines'                => $machineDetails,
            'manual_production'       => [
                'enabled'             => $supportsManual,
                'configured_capacity' => (float) ($section->manual_capacity_per_hour ?? 0.0),
                'effective_capacity'  => round($manualCapacity, 4),
            ],
            'supports_manual'         => $supportsManual,
            'manual_capacity'         => round($manualCapacity, 4),
        ];
    }

    /**
     * Calculate Section Labor Cost Per Hour, timeline windows, and active shifts.
     */
    public function calculateLaborCost(WorkCenterSection $section): array
    {
        $tenantId = (int) $section->tenant_id;

        $shifts = $section->shifts->where('tenant_id', $tenantId)->filter(fn($s) => (bool) ($s->active ?? true));

        if ($shifts->isEmpty()) {
            return [
                'total_scheduled_labor_cost' => 0.0,
                'unique_operating_hours'     => 0.0,
                'labor_cost_per_hour'        => 0.0,
                'has_overlapping_shifts'     => false,
                'shifts'                     => [],
                'windows'                    => [],
            ];
        }

        $shiftData = [];
        $shiftMap  = [];

        foreach ($shifts as $shift) {
            $workersRequired = (int) ($shift->pivot->target_headcount ?? $shift->pivot->workers_required ?? 1);
            $hourlyRate      = $shift->hourly_labor_rate !== null ? (float) $shift->hourly_labor_rate : 0.0;
            $shiftHourlyCost = round($workersRequired * $hourlyRate, 4);

            $startMin = $this->parseTimeToMinutes($shift->start_time ?? '00:00');
            $endMin   = $this->parseTimeToMinutes($shift->end_time ?? '00:00');

            $isOvernight = ($endMin <= $startMin);
            $durationMin = $isOvernight ? (1440 - $startMin + $endMin) : ($endMin - $startMin);
            if ($durationMin === 0) {
                $durationMin = 1440; // 24-hour shift if start == end
            }

            $item = [
                'id'                        => $shift->id,
                'code'                      => $shift->code,
                'name'                      => $shift->name,
                'start_time'                => substr((string) $shift->start_time, 0, 5),
                'end_time'                  => substr((string) $shift->end_time, 0, 5),
                'start_minute'              => $startMin,
                'end_minute'                => $endMin,
                'is_overnight'              => $isOvernight,
                'duration_minutes'          => $durationMin,
                'duration_hours'            => round($durationMin / 60, 4),
                'target_headcount'          => $workersRequired,
                'workers_required'          => $workersRequired,
                'hourly_labor_rate'         => $hourlyRate,
                'shift_labor_cost_per_hour' => $shiftHourlyCost,
            ];

            $shiftData[]          = $item;
            $shiftMap[$shift->id] = $item;
        }

        // Build 1440-minute active shift map across the 24-hour cycle
        $minuteActiveShifts = array_fill(0, 1440, []);
        $covered            = array_fill(0, 1440, false);

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

        $totalCoveredMinutes = count(array_filter($covered));
        if ($totalCoveredMinutes === 0) {
            return [
                'total_scheduled_labor_cost' => 0.0,
                'unique_operating_hours'     => 0.0,
                'labor_cost_per_hour'        => 0.0,
                'has_overlapping_shifts'     => false,
                'shifts'                     => $shiftData,
                'windows'                    => [],
            ];
        }

        // Find chronological start minute T0:
        // If there are gaps in the 24h cycle, anchor to the gap-to-operating transition at or after earliest shift start
        $earliestShiftStart = min(array_column($shiftData, 'start_minute'));
        $hasGaps            = ($totalCoveredMinutes < 1440);

        if ($hasGaps) {
            $t0 = null;
            for ($i = 0; $i < 1440; $i++) {
                $m     = ($earliestShiftStart + $i) % 1440;
                $prevM = ($m - 1 + 1440) % 1440;
                if ($covered[$m] && !$covered[$prevM]) {
                    $t0 = $m;
                    break;
                }
            }
            if ($t0 === null) {
                $t0 = $earliestShiftStart;
            }
        } else {
            $t0 = $earliestShiftStart;
        }

        // Unroll timeline from T0 to T0 + 1440 and extract contiguous intervals with identical active shifts
        $windows             = [];
        $currentWindowStart  = null;
        $currentActiveIds    = null;

        for ($offset = 0; $offset <= 1440; $offset++) {
            $T         = $t0 + $offset;
            $m         = $T % 1440;
            $activeIds = ($offset < 1440) ? $minuteActiveShifts[$m] : [];
            sort($activeIds);
            $activeKey = implode(',', $activeIds);

            $currentKey = ($currentActiveIds !== null) ? implode(',', $currentActiveIds) : '';

            if ($currentWindowStart !== null && $activeKey !== $currentKey) {
                if (!empty($currentActiveIds)) {
                    $winDurationMin   = $T - $currentWindowStart;
                    $winDurationHours = round($winDurationMin / 60, 4);

                    $windows[] = $this->buildWindowPayload(
                        $currentWindowStart,
                        $T,
                        $winDurationHours,
                        $currentActiveIds,
                        $shiftMap
                    );
                }
                $currentWindowStart = null;
                $currentActiveIds   = null;
            }

            if (!empty($activeIds) && $currentWindowStart === null) {
                $currentWindowStart = $T;
                $currentActiveIds   = $activeIds;
            }
        }

        $totalScheduledLaborCost = 0.0;
        $uniqueOperatingHours    = 0.0;
        $hasOverlappingShifts    = false;

        foreach ($windows as $win) {
            $totalScheduledLaborCost += $win['window_cost'];
            $uniqueOperatingHours    += $win['duration_hours'];
            if ($win['is_overlapping']) {
                $hasOverlappingShifts = true;
            }
        }

        $totalScheduledLaborCost = round($totalScheduledLaborCost, 4);
        $uniqueOperatingHours    = round($uniqueOperatingHours, 4);
        $laborCostPerHour        = $uniqueOperatingHours > 0
            ? round($totalScheduledLaborCost / $uniqueOperatingHours, 4)
            : 0.0;

        return [
            'total_scheduled_labor_cost' => $totalScheduledLaborCost,
            'unique_operating_hours'     => $uniqueOperatingHours,
            'labor_cost_per_hour'        => $laborCostPerHour,
            'has_overlapping_shifts'     => $hasOverlappingShifts,
            'shifts'                     => $shiftData,
            'windows'                    => $windows,
            'timeline_windows'           => $windows,
        ];
    }

    /**
     * Compute full calculation payload.
     */
    public function calculate(WorkCenterSection $section): array
    {
        return [
            'capacity'      => $this->calculateCapacity($section),
            'labor_cost'    => $this->calculateLaborCost($section),
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate and persist Section calculated metrics (safely bypassed during schema redesign).
     */
    public function calculateAndSave(WorkCenterSection $section): WorkCenterSection
    {
        $fresh = $section->fresh(['workCenter', 'machines']);

        if ($fresh && $fresh->workCenter) {
            app(WorkCenterCalculationService::class)->calculateAndSave($fresh->workCenter);
        }

        return $fresh ?: $section;
    }

    private function parseTimeToMinutes(string $timeStr): int
    {
        $parts = explode(':', trim($timeStr));
        $h     = (int) ($parts[0] ?? 0);
        $m     = (int) ($parts[1] ?? 0);

        return ($h * 60 + $m) % 1440;
    }

    private function buildWindowPayload(
        int $startAbsMin,
        int $endAbsMin,
        float $durationHours,
        array $activeShiftIds,
        array $shiftMap
    ): array {
        $startClockMin = $startAbsMin % 1440;
        $endClockMin   = $endAbsMin % 1440;

        $startH = (int) floor($startClockMin / 60);
        $startM = $startClockMin % 60;
        $endH   = (int) floor($endClockMin / 60);
        $endM   = $endClockMin % 60;

        $startTimeStr = sprintf('%02d:%02d', $startH, $startM);
        $endTimeStr   = sprintf('%02d:%02d', $endH, $endM);

        $isOvernight = ((int) floor($startAbsMin / 1440)) !== ((int) floor(($endAbsMin - 1) / 1440));

        $endTimeLabel = $isOvernight ? "{$endTimeStr} next day" : $endTimeStr;
        $windowLabel  = "{$startTimeStr}–{$endTimeLabel}";

        $activeShifts       = [];
        $combinedWorkers    = 0;
        $combinedHourlyCost = 0.0;

        foreach ($activeShiftIds as $sId) {
            if (isset($shiftMap[$sId])) {
                $s                    = $shiftMap[$sId];
                $activeShifts[]       = [
                    'id'                        => $s['id'],
                    'code'                      => $s['code'],
                    'name'                      => $s['name'],
                    'workers_required'          => $s['workers_required'],
                    'hourly_labor_rate'         => $s['hourly_labor_rate'],
                    'shift_labor_cost_per_hour' => $s['shift_labor_cost_per_hour'],
                ];
                $combinedWorkers    += $s['workers_required'];
                $combinedHourlyCost += $s['shift_labor_cost_per_hour'];
            }
        }

        $combinedHourlyCost = round($combinedHourlyCost, 4);
        $windowCost         = round($durationHours * $combinedHourlyCost, 4);
        $isOverlapping      = count($activeShifts) > 1;

        return [
            'start_time'           => $startTimeStr,
            'end_time'             => $endTimeStr,
            'end_time_label'       => $endTimeLabel,
            'window'               => $windowLabel,
            'window_label'         => $windowLabel,
            'duration_hours'       => $durationHours,
            'is_overnight'         => $isOvernight,
            'is_overlap'           => $isOverlapping,
            'is_overlapping'       => $isOverlapping,
            'active_shifts'        => $activeShifts,
            'active_shift_names'   => array_column($activeShifts, 'name'),
            'active_workers'       => $combinedWorkers,
            'combined_workers'     => $combinedWorkers,
            'combined_hourly_cost' => $combinedHourlyCost,
            'window_cost'          => $windowCost,
        ];
    }
}
