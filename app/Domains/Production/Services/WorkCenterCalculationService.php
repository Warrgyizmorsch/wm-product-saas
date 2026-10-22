<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;

class WorkCenterCalculationService
{
    public function __construct(
        private readonly ?SectionCalculationService $sectionCalculationService = null,
    ) {}

    protected function getSectionCalculationService(): SectionCalculationService
    {
        return $this->sectionCalculationService ?? app(SectionCalculationService::class);
    }

    /**
     * Calculate Work Center Capacity Per Hour over its unique operating timeline.
     *
     * Formula:
     * Work Center Capacity per Hour = Total Theoretical Capacity / Unique Work Center Operating Hours
     *
     * Where:
     * - Window Capacity per Hour = Sum of capacities per hour of all active Sections
     * - Window Theoretical Capacity = Window Capacity per Hour × Window Duration
     * - Total Theoretical Capacity = Sum of all window theoretical capacities
     * - Unique Work Center Operating Hours = Duration of the union of all assigned Section shift intervals
     */
    public function calculateCapacity(WorkCenter $workCenter): array
    {
        $tenantId = (int) $workCenter->tenant_id;

        $sections = $workCenter->relationLoaded('sections')
            ? $workCenter->sections->where('tenant_id', $tenantId)->filter(fn($sec) => $sec->isActive())
            : $workCenter->sections()->where('tenant_id', $tenantId)->active()->with(['machines', 'shifts'])->get();

        if ($sections->isEmpty()) {
            return [
                'capacity_per_hour'          => 0.0,
                'total_theoretical_capacity' => 0.0,
                'unique_operating_hours'     => 0.0,
                'capacity_unit'              => 'units/hr',
                'sections'                   => [],
                'windows'                    => [],
                'timeline_windows'           => [],
                'gaps'                       => [],
            ];
        }

        $sectionCalcService = $this->getSectionCalculationService();
        $sectionData = [];
        $sectionMap  = [];
        $allShiftIntervals = [];

        foreach ($sections as $section) {
            $capInfo = $sectionCalcService->calculateCapacity($section);
            $sectionCap = (float) ($capInfo['total_capacity'] ?? 0.0);

            $shifts = $section->relationLoaded('shifts')
                ? $section->shifts->where('tenant_id', $tenantId)->filter(fn($s) => ($s->active ?? true) && ($s->pivot->active ?? true))
                : $section->shifts()->where('production_shifts.tenant_id', $tenantId)->where('production_shifts.active', true)->where('production_section_shifts.active', true)->get();

            $shiftIntervals = [];
            foreach ($shifts as $shift) {
                $startMin = $this->parseTimeToMinutes($shift->start_time ?? '00:00');
                $endMin   = $this->parseTimeToMinutes($shift->end_time ?? '00:00');
                $isOvernight = ($endMin <= $startMin);
                $durationMin = $isOvernight ? (1440 - $startMin + $endMin) : ($endMin - $startMin);
                if ($durationMin === 0) {
                    $durationMin = 1440;
                }

                $interval = [
                    'shift_id'     => $shift->id,
                    'name'         => $shift->name,
                    'start_time'   => substr((string) $shift->start_time, 0, 5),
                    'end_time'     => substr((string) $shift->end_time, 0, 5),
                    'start_minute' => $startMin,
                    'end_minute'   => $endMin,
                    'is_overnight' => $isOvernight,
                    'duration_min' => $durationMin,
                ];

                $shiftIntervals[] = $interval;
                $allShiftIntervals[] = $interval;
            }

            $sectionItem = [
                'id'                         => $section->id,
                'code'                       => $section->code,
                'name'                       => $section->name,
                'capacity_per_hour'          => round($sectionCap, 4),
                'machine_count'              => $section->relationLoaded('machines') ? $section->machines->count() : $section->machines()->count(),
                'supports_manual_production' => (bool) $section->supports_manual_production,
                'manual_capacity_per_hour'   => (float) ($section->manual_capacity_per_hour ?? 0.0),
                'shifts_count'               => count($shiftIntervals),
                'shift_intervals'            => $shiftIntervals,
            ];

            $sectionData[] = $sectionItem;
            $sectionMap[$section->id] = $sectionItem;
        }

        if (empty($allShiftIntervals)) {
            return [
                'capacity_per_hour'          => 0.0,
                'total_theoretical_capacity' => 0.0,
                'unique_operating_hours'     => 0.0,
                'capacity_unit'              => 'units/hr',
                'sections'                   => $sectionData,
                'windows'                    => [],
                'timeline_windows'           => [],
                'gaps'                       => [],
            ];
        }

        // Map minute [0..1439] -> active Section IDs
        $minuteActiveSections = array_fill(0, 1440, []);
        $covered = array_fill(0, 1440, false);

        foreach ($sectionData as $sec) {
            $secId = $sec['id'];
            $secActiveMinuteFlags = array_fill(0, 1440, false);

            foreach ($sec['shift_intervals'] as $s) {
                $sMin   = $s['start_minute'];
                $eMin   = $s['end_minute'];
                $isOver = $s['is_overnight'];

                for ($m = 0; $m < 1440; $m++) {
                    $isActive = $isOver
                        ? ($m >= $sMin || $m < $eMin)
                        : ($m >= $sMin && $m < $eMin);

                    if ($isActive) {
                        $secActiveMinuteFlags[$m] = true;
                    }
                }
            }

            for ($m = 0; $m < 1440; $m++) {
                if ($secActiveMinuteFlags[$m]) {
                    $minuteActiveSections[$m][] = $secId;
                    $covered[$m] = true;
                }
            }
        }

        $totalCoveredMinutes = count(array_filter($covered));
        if ($totalCoveredMinutes === 0) {
            return [
                'capacity_per_hour'          => 0.0,
                'total_theoretical_capacity' => 0.0,
                'unique_operating_hours'     => 0.0,
                'capacity_unit'              => 'units/hr',
                'sections'                   => $sectionData,
                'windows'                    => [],
                'timeline_windows'           => [],
                'gaps'                       => [],
            ];
        }

        // Find chronological start minute T0:
        $earliestShiftStart = min(array_column($allShiftIntervals, 'start_minute'));
        $hasGaps = ($totalCoveredMinutes < 1440);

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

        // Unroll timeline from T0 to T0 + 1440 and extract contiguous intervals
        $windows = [];
        $gaps    = [];
        $currentIntervalStart = $t0;
        $currentIntervalActive = ($minuteActiveSections[$t0 % 1440] ?? []);
        sort($currentIntervalActive);

        for ($offset = 1; $offset < 1440; $offset++) {
            $T = $t0 + $offset;
            $m = $T % 1440;
            $activeIds = $minuteActiveSections[$m] ?? [];
            sort($activeIds);

            if ($activeIds !== $currentIntervalActive) {
                $durationMin   = $T - $currentIntervalStart;
                $durationHours = round($durationMin / 60, 4);

                if (!empty($currentIntervalActive)) {
                    $windows[] = $this->buildCapacityWindowPayload(
                        $currentIntervalStart,
                        $T,
                        $durationHours,
                        $currentIntervalActive,
                        $sectionMap
                    );
                } else {
                    $gaps[] = $this->buildGapPayload(
                        $currentIntervalStart,
                        $T,
                        $durationHours
                    );
                }

                $currentIntervalStart  = $T;
                $currentIntervalActive = $activeIds;
            }
        }

        // Close the final interval up to T0 + 1440
        $endT = $t0 + 1440;
        if ($endT > $currentIntervalStart) {
            $durationMin   = $endT - $currentIntervalStart;
            $durationHours = round($durationMin / 60, 4);

            if (!empty($currentIntervalActive)) {
                $windows[] = $this->buildCapacityWindowPayload(
                    $currentIntervalStart,
                    $endT,
                    $durationHours,
                    $currentIntervalActive,
                    $sectionMap
                );
            } else {
                $gaps[] = $this->buildGapPayload(
                    $currentIntervalStart,
                    $endT,
                    $durationHours
                );
            }
        }

        $totalTheoreticalCapacity = 0.0;
        $uniqueOperatingHours     = 0.0;

        foreach ($windows as $win) {
            $totalTheoreticalCapacity += $win['theoretical_capacity'];
            $uniqueOperatingHours     += $win['duration_hours'];
        }

        $totalTheoreticalCapacity = round($totalTheoreticalCapacity, 4);
        $uniqueOperatingHours     = round($uniqueOperatingHours, 4);
        $capacityPerHour          = $uniqueOperatingHours > 0
            ? round($totalTheoreticalCapacity / $uniqueOperatingHours, 4)
            : 0.0;

        return [
            'capacity_per_hour'          => $capacityPerHour,
            'total_theoretical_capacity' => $totalTheoreticalCapacity,
            'unique_operating_hours'     => $uniqueOperatingHours,
            'capacity_unit'              => 'units/hr',
            'sections'                   => $sectionData,
            'windows'                    => $windows,
            'timeline_windows'           => $windows,
            'gaps'                       => $gaps,
        ];
    }

    /**
     * Calculate Work Center Labor Cost Per Hour from individual Section/shift assignments.
     *
     * Formula:
     * Assignment Labor Cost per Hour = Shift Hourly Labor Rate per Worker × Section-Specific Assigned Worker Count
     * Work Center Labor Cost per Hour = Total Scheduled Labor Cost / Unique Work Center Operating Hours
     */
    public function calculateLaborCost(WorkCenter $workCenter): array
    {
        $tenantId = (int) $workCenter->tenant_id;

        $sections = $workCenter->relationLoaded('sections')
            ? $workCenter->sections->where('tenant_id', $tenantId)->filter(fn($sec) => $sec->isActive())
            : $workCenter->sections()->where('tenant_id', $tenantId)->active()->with(['shifts'])->get();

        if ($sections->isEmpty()) {
            return [
                'labor_cost_per_hour'        => 0.0,
                'total_scheduled_labor_cost' => 0.0,
                'unique_operating_hours'     => 0.0,
                'has_overlapping_assignments'=> false,
                'has_missing_rates'          => false,
                'assignments'                => [],
                'windows'                    => [],
                'timeline_windows'           => [],
                'gaps'                       => [],
            ];
        }

        $assignmentData = [];
        $assignmentMap  = [];
        $hasMissingRates = false;

        foreach ($sections as $section) {
            $shifts = $section->relationLoaded('shifts')
                ? $section->shifts->where('tenant_id', $tenantId)->filter(fn($s) => ($s->active ?? true) && ($s->pivot->active ?? true))
                : $section->shifts()->where('production_shifts.tenant_id', $tenantId)->where('production_shifts.active', true)->where('production_section_shifts.active', true)->get();

            foreach ($shifts as $shift) {
                $workersRequired = (int) ($shift->pivot->target_headcount ?? $shift->pivot->workers_required ?? 1);
                $isRateMissing   = ($shift->hourly_labor_rate === null);
                if ($isRateMissing) {
                    $hasMissingRates = true;
                }

                $hourlyRate      = $shift->hourly_labor_rate !== null ? (float) $shift->hourly_labor_rate : null;
                $effectiveRate   = (float) ($shift->hourly_labor_rate ?? 0.0);
                $hourlyCost      = round($workersRequired * $effectiveRate, 4);

                $startMin = $this->parseTimeToMinutes($shift->start_time ?? '00:00');
                $endMin   = $this->parseTimeToMinutes($shift->end_time ?? '00:00');

                $isOvernight = ($endMin <= $startMin);
                $durationMin = $isOvernight ? (1440 - $startMin + $endMin) : ($endMin - $startMin);
                if ($durationMin === 0) {
                    $durationMin = 1440;
                }

                $assignmentKey = "{$section->id}_{$shift->id}";

                $item = [
                    'assignment_key'               => $assignmentKey,
                    'section_id'                   => $section->id,
                    'section_code'                 => $section->code,
                    'section_name'                 => $section->name,
                    'shift_id'                     => $shift->id,
                    'shift_code'                   => $shift->code,
                    'shift_name'                   => $shift->name,
                    'start_time'                   => substr((string) $shift->start_time, 0, 5),
                    'end_time'                     => substr((string) $shift->end_time, 0, 5),
                    'start_minute'                 => $startMin,
                    'end_minute'                   => $endMin,
                    'is_overnight'                 => $isOvernight,
                    'duration_minutes'             => $durationMin,
                    'duration_hours'               => round($durationMin / 60, 4),
                    'target_headcount'             => $workersRequired,
                    'workers_required'             => $workersRequired,
                    'hourly_labor_rate'            => $hourlyRate,
                    'is_rate_missing'              => $isRateMissing,
                    'effective_hourly_rate'        => $effectiveRate,
                    'assignment_labor_cost_per_hour'=> $hourlyCost,
                ];

                $assignmentData[] = $item;
                $assignmentMap[$assignmentKey] = $item;
            }
        }

        if (empty($assignmentData)) {
            return [
                'labor_cost_per_hour'        => 0.0,
                'total_scheduled_labor_cost' => 0.0,
                'unique_operating_hours'     => 0.0,
                'has_overlapping_assignments'=> false,
                'has_missing_rates'          => false,
                'assignments'                => [],
                'windows'                    => [],
                'timeline_windows'           => [],
                'gaps'                       => [],
            ];
        }

        // Map minute [0..1439] -> active Assignment Keys
        $minuteActiveAssignments = array_fill(0, 1440, []);
        $covered = array_fill(0, 1440, false);

        foreach ($assignmentData as $a) {
            $sMin   = $a['start_minute'];
            $eMin   = $a['end_minute'];
            $isOver = $a['is_overnight'];

            for ($m = 0; $m < 1440; $m++) {
                $isActive = $isOver
                    ? ($m >= $sMin || $m < $eMin)
                    : ($m >= $sMin && $m < $eMin);

                if ($isActive) {
                    $minuteActiveAssignments[$m][] = $a['assignment_key'];
                    $covered[$m] = true;
                }
            }
        }

        $totalCoveredMinutes = count(array_filter($covered));
        if ($totalCoveredMinutes === 0) {
            return [
                'labor_cost_per_hour'        => 0.0,
                'total_scheduled_labor_cost' => 0.0,
                'unique_operating_hours'     => 0.0,
                'has_overlapping_assignments'=> false,
                'has_missing_rates'          => $hasMissingRates,
                'assignments'                => $assignmentData,
                'windows'                    => [],
                'timeline_windows'           => [],
                'gaps'                       => [],
            ];
        }

        // Chronological anchor T0:
        $earliestShiftStart = min(array_column($assignmentData, 'start_minute'));
        $hasGaps = ($totalCoveredMinutes < 1440);

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

        // Unroll timeline from T0 to T0 + 1440 and extract contiguous intervals
        $windows = [];
        $gaps    = [];
        $currentIntervalStart = $t0;
        $currentIntervalActive = ($minuteActiveAssignments[$t0 % 1440] ?? []);
        sort($currentIntervalActive);

        for ($offset = 1; $offset < 1440; $offset++) {
            $T = $t0 + $offset;
            $m = $T % 1440;
            $activeKeys = $minuteActiveAssignments[$m] ?? [];
            sort($activeKeys);

            if ($activeKeys !== $currentIntervalActive) {
                $durationMin   = $T - $currentIntervalStart;
                $durationHours = round($durationMin / 60, 4);

                if (!empty($currentIntervalActive)) {
                    $windows[] = $this->buildLaborWindowPayload(
                        $currentIntervalStart,
                        $T,
                        $durationHours,
                        $currentIntervalActive,
                        $assignmentMap
                    );
                } else {
                    $gaps[] = $this->buildGapPayload(
                        $currentIntervalStart,
                        $T,
                        $durationHours
                    );
                }

                $currentIntervalStart  = $T;
                $currentIntervalActive = $activeKeys;
            }
        }

        // Close the final interval up to T0 + 1440
        $endT = $t0 + 1440;
        if ($endT > $currentIntervalStart) {
            $durationMin   = $endT - $currentIntervalStart;
            $durationHours = round($durationMin / 60, 4);

            if (!empty($currentIntervalActive)) {
                $windows[] = $this->buildLaborWindowPayload(
                    $currentIntervalStart,
                    $endT,
                    $durationHours,
                    $currentIntervalActive,
                    $assignmentMap
                );
            } else {
                $gaps[] = $this->buildGapPayload(
                    $currentIntervalStart,
                    $endT,
                    $durationHours
                );
            }
        }

        $totalScheduledLaborCost    = 0.0;
        $uniqueOperatingHours       = 0.0;
        $hasOverlappingAssignments  = false;

        foreach ($windows as $win) {
            $totalScheduledLaborCost += $win['window_cost'];
            $uniqueOperatingHours    += $win['duration_hours'];
            if ($win['is_overlapping']) {
                $hasOverlappingAssignments = true;
            }
        }

        $totalScheduledLaborCost = round($totalScheduledLaborCost, 4);
        $uniqueOperatingHours    = round($uniqueOperatingHours, 4);
        $laborCostPerHour        = $uniqueOperatingHours > 0
            ? round($totalScheduledLaborCost / $uniqueOperatingHours, 4)
            : 0.0;

        return [
            'labor_cost_per_hour'        => $laborCostPerHour,
            'total_scheduled_labor_cost' => $totalScheduledLaborCost,
            'unique_operating_hours'     => $uniqueOperatingHours,
            'has_overlapping_assignments'=> $hasOverlappingAssignments,
            'has_missing_rates'          => $hasMissingRates,
            'assignments'                => $assignmentData,
            'windows'                    => $windows,
            'timeline_windows'           => $windows,
            'gaps'                       => $gaps,
        ];
    }

    /**
     * Compute full calculation payload.
     */
    public function calculate(WorkCenter $workCenter): array
    {
        return [
            'capacity'      => $this->calculateCapacity($workCenter),
            'labor_cost'    => $this->calculateLaborCost($workCenter),
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate and persist Work Center metrics and calculation_details JSON snapshot.
     */
    public function calculateAndSave(WorkCenter $workCenter): WorkCenter
    {
        $calc = $this->calculate($workCenter);

        $workCenter->update([
            'capacity_per_hour'          => $calc['capacity']['capacity_per_hour'],
            'total_theoretical_capacity' => $calc['capacity']['total_theoretical_capacity'],
            'labor_cost_per_hour'        => $calc['labor_cost']['labor_cost_per_hour'],
            'total_scheduled_labor_cost' => $calc['labor_cost']['total_scheduled_labor_cost'],
            'unique_operating_hours'     => $calc['capacity']['unique_operating_hours'] ?: $calc['labor_cost']['unique_operating_hours'],
            'calculation_details'        => $calc,
        ]);

        return $workCenter->fresh(['sections.machines', 'sections.shifts']);
    }

    private function parseTimeToMinutes(string $timeStr): int
    {
        $parts = explode(':', trim($timeStr));
        $h     = (int) ($parts[0] ?? 0);
        $m     = (int) ($parts[1] ?? 0);

        return ($h * 60 + $m) % 1440;
    }

    private function buildCapacityWindowPayload(
        int $startAbsMin,
        int $endAbsMin,
        float $durationHours,
        array $activeSectionIds,
        array $sectionMap
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

        $activeSections          = [];
        $combinedCapacityPerHour = 0.0;

        foreach ($activeSectionIds as $secId) {
            if (isset($sectionMap[$secId])) {
                $sec = $sectionMap[$secId];
                $activeSections[] = [
                    'id'                => $sec['id'],
                    'code'              => $sec['code'],
                    'name'              => $sec['name'],
                    'capacity_per_hour' => $sec['capacity_per_hour'],
                ];
                $combinedCapacityPerHour += $sec['capacity_per_hour'];
            }
        }

        $combinedCapacityPerHour = round($combinedCapacityPerHour, 4);
        $theoreticalCapacity     = round($durationHours * $combinedCapacityPerHour, 4);
        $isOverlapping           = count($activeSections) > 1;

        return [
            'start_time'               => $startTimeStr,
            'end_time'                 => $endTimeStr,
            'end_time_label'           => $endTimeLabel,
            'window'                   => $windowLabel,
            'window_label'             => $windowLabel,
            'duration_hours'           => $durationHours,
            'is_overnight'             => $isOvernight,
            'is_overlap'               => $isOverlapping,
            'is_overlapping'           => $isOverlapping,
            'active_sections'          => $activeSections,
            'active_section_names'     => array_column($activeSections, 'name'),
            'combined_capacity_per_hour'=> $combinedCapacityPerHour,
            'combined_capacity'        => $combinedCapacityPerHour,
            'theoretical_capacity'     => $theoreticalCapacity,
            'window_theoretical_capacity' => $theoreticalCapacity,
        ];
    }

    private function buildLaborWindowPayload(
        int $startAbsMin,
        int $endAbsMin,
        float $durationHours,
        array $activeAssignmentKeys,
        array $assignmentMap
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

        $activeAssignments  = [];
        $combinedWorkers    = 0;
        $combinedHourlyCost = 0.0;
        $windowHasMissingRate = false;

        foreach ($activeAssignmentKeys as $key) {
            if (isset($assignmentMap[$key])) {
                $a = $assignmentMap[$key];
                $activeAssignments[] = [
                    'assignment_key'                => $a['assignment_key'],
                    'section_id'                    => $a['section_id'],
                    'section_code'                  => $a['section_code'],
                    'section_name'                  => $a['section_name'],
                    'shift_id'                      => $a['shift_id'],
                    'shift_code'                    => $a['shift_code'],
                    'shift_name'                    => $a['shift_name'],
                    'workers_required'              => $a['workers_required'],
                    'hourly_labor_rate'             => $a['hourly_labor_rate'],
                    'is_rate_missing'               => $a['is_rate_missing'],
                    'effective_hourly_rate'         => $a['effective_hourly_rate'],
                    'assignment_labor_cost_per_hour'=> $a['assignment_labor_cost_per_hour'],
                ];
                $combinedWorkers    += $a['workers_required'];
                $combinedHourlyCost += $a['assignment_labor_cost_per_hour'];
                if ($a['is_rate_missing']) {
                    $windowHasMissingRate = true;
                }
            }
        }

        $combinedHourlyCost = round($combinedHourlyCost, 4);
        $windowCost         = round($durationHours * $combinedHourlyCost, 4);
        $isOverlapping      = count($activeAssignments) > 1;

        return [
            'start_time'               => $startTimeStr,
            'end_time'                 => $endTimeStr,
            'end_time_label'           => $endTimeLabel,
            'window'                   => $windowLabel,
            'window_label'             => $windowLabel,
            'duration_hours'           => $durationHours,
            'is_overnight'             => $isOvernight,
            'is_overlap'               => $isOverlapping,
            'is_overlapping'           => $isOverlapping,
            'active_assignments'       => $activeAssignments,
            'active_workers'           => $combinedWorkers,
            'combined_workers'         => $combinedWorkers,
            'combined_hourly_cost'     => $combinedHourlyCost,
            'combined_labor_cost_per_hour' => $combinedHourlyCost,
            'window_cost'              => $windowCost,
            'has_missing_rates'        => $windowHasMissingRate,
        ];
    }

    private function buildGapPayload(
        int $startAbsMin,
        int $endAbsMin,
        float $durationHours
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

        return [
            'start_time'     => $startTimeStr,
            'end_time'       => $endTimeStr,
            'end_time_label' => $endTimeLabel,
            'window'         => $windowLabel,
            'window_label'   => $windowLabel,
            'duration_hours' => $durationHours,
            'is_overnight'   => $isOvernight,
            'is_gap'         => true,
        ];
    }
}
