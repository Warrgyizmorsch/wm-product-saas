{{--
    Work Center Calculation Breakdown Modal (WC-IMPL-20)
    Params: $workCenter (required), $snapshot (optional WorkCenterCalculation; default = latest),
            $modalElementId (optional; default 'workCenterCalculationModal').
    A historical snapshot shows only what it stored: no live overhead, no live section list,
    and the section figures come from the section snapshots that this Work Center snapshot rolled up.
--}}
@php
    $latestWcSnapshot = $workCenter->latestCalculation;
    $wcSnapshot = $snapshot ?? $latestWcSnapshot;
    $isLatest = !$wcSnapshot || ($latestWcSnapshot && (int) $latestWcSnapshot->id === (int) $wcSnapshot->id);
    $modalElementId = $modalElementId ?? 'workCenterCalculationModal';
    $wcDetails = $wcSnapshot?->calculation_details ?? [];
    $wcCapPerHour = (float) ($wcSnapshot?->capacity_per_hour ?? 0);
    $wcDailyCap = (float) ($wcSnapshot?->daily_capacity ?? 0);
    $wcLaborCostPerHour = (float) ($wcSnapshot?->labor_cost_per_hour ?? 0);
    $wcDailyLaborCost = (float) ($wcSnapshot?->total_daily_labor_cost ?? 0);
    $wcMachCostPerHour = (float) ($wcSnapshot?->machine_cost_per_hour ?? 0);
    $wcDailyMachCost = (float) ($wcSnapshot?->total_daily_machine_cost ?? 0);
    $wcOverheadDaily = (float) ($wcSnapshot?->overhead_cost ?? 0); // snapshot only (WC-IMPL-16 / WC-IMPL-20)
    $wcTotalDailyCost = (float) ($wcSnapshot?->total_daily_cost ?? 0);
    $wcTotalCostPerHour = (float) ($wcSnapshot?->total_cost_per_hour ?? 0);
    $wcUniqOperatingHours = (float) ($wcSnapshot?->unique_operating_hours ?? 0);

    $wcSectionsBreakdown = $wcDetails['sections_contribution'] ?? $wcDetails['sections'] ?? [];
    $wcDirectStaff = $wcDetails['direct_work_center_staff']['employees'] ?? $wcDetails['direct_personnel']['employees'] ?? [];
    $wcDirectStaffDaily = (float) ($wcSnapshot?->direct_staff_daily_labor_cost ?? $wcDetails['direct_work_center_staff']['total_daily_cost'] ?? $wcDetails['direct_personnel']['total_daily_cost'] ?? 0);
    // null = N/A (output 0, cost > 0 — WC-23 / WC-29)
    $wcCostPerUnit = $wcSnapshot
        ? ($wcSnapshot->cost_per_unit === null ? null : (float) $wcSnapshot->cost_per_unit)
        : cost_per_unit_value($wcTotalDailyCost, $wcDailyCap);
    $wcTimelineIntervals = $wcDetails['timeline_intervals'] ?? [];
    $wcTimelineGaps = $wcDetails['timeline_gaps'] ?? [];

    // Total Sections Daily Operating Cost & Labor Subtotals
    $totalSectionDailyLabor = (float) array_sum(array_column($wcSectionsBreakdown, 'total_daily_labor_cost'));
    $totalSectionDailyMach = (float) array_sum(array_column($wcSectionsBreakdown, 'total_daily_machine_cost'));
    $totalSectionsDailyCost = (float) array_sum(array_column($wcSectionsBreakdown, 'total_daily_operating_cost'));
    if ($totalSectionsDailyCost <= 0) {
        $totalSectionsDailyCost = $totalSectionDailyLabor + $totalSectionDailyMach;
    }

    // Direct Staff Subtotals
    $totalDirectMonthlyCTC = (float) array_sum(array_map(fn($e) => (float) ($e['monthly_salary'] ?? $e['monthly_ctc'] ?? $e['hrms_net_monthly_salary'] ?? 0), $wcDirectStaff));
    $stationFixedBurdenPerUnit = cost_per_unit_value($wcDirectStaffDaily + $wcOverheadDaily, $wcDailyCap);
    $wcSecCostPerUnit = cost_per_unit_value($totalSectionsDailyCost, $wcDailyCap);

    // Timeline Matrix: From snapshot JSON or computed on the fly from active sections
    $wcTimelineMatrix = $wcDetails['timeline_matrix'] ?? null;
    // WC-IMPL-15: each sections_contribution item names the section snapshot it used (section_calculation_id).
    // That exact section snapshot is used in both views. Without the key (older Work Center snapshots):
    //   latest view → each section's latest snapshot;
    //   historical view → same batch, else the newest section snapshot recorded at or before this Work Center snapshot.
    $wcBreakdownSecIds = array_values(array_filter(array_map(fn($b) => $b['section_id'] ?? null, $wcSectionsBreakdown)));
    $wcBreakdownBySec = collect($wcSectionsBreakdown)->keyBy('section_id');
    $usedSecCalcIds = array_values(array_filter(array_map(fn($b) => $b['section_calculation_id'] ?? null, $wcSectionsBreakdown)));
    $usedSecCalcs = empty($usedSecCalcIds)
        ? collect()
        : \App\Domains\Production\Models\WorkCenterSectionCalculation::whereIn('id', $usedSecCalcIds)->get()->keyBy('section_id');
    if ($isLatest) {
        $activeSecModels = $workCenter->sections->filter(fn($s) => $s->isActive());
        $secCalcMap = null;
    } else {
        $activeSecModels = $workCenter->sections->whereIn('id', $wcBreakdownSecIds);
        $secCalcMap = [];
        foreach ($wcBreakdownSecIds as $bSecId) {
            if ($usedSecCalcs->has($bSecId)) {
                $secCalcMap[$bSecId] = $usedSecCalcs->get($bSecId);
                continue;
            }
            $q = \App\Domains\Production\Models\WorkCenterSectionCalculation::where('section_id', $bSecId);
            $found = (clone $q)->where('work_center_calculation_id', $wcSnapshot->id)->latest('id')->first();
            if (!$found && $wcSnapshot->batch_id) {
                $found = (clone $q)->where('batch_id', $wcSnapshot->batch_id)->latest('id')->first();
            }
            if (!$found && $wcSnapshot->created_at) {
                $found = (clone $q)->where('created_at', '<=', $wcSnapshot->created_at)->latest('id')->first();
            }
            $secCalcMap[$bSecId] = $found;
        }
    }
    $secCalcFor = fn($secModel) => $secModel
        ? ($usedSecCalcs->get($secModel->id)
            ?? ($secCalcMap === null ? $secModel->latestCalculation : ($secCalcMap[$secModel->id] ?? null)))
        : null;
    // Drill-down target for a section: the exact section snapshot this Work Center snapshot used (WC-IMPL-15),
    // else (older Work Center snapshots) the page's latest section modal.
    $secDrillAttrs = function ($secModel) use ($usedSecCalcs, $workCenter) {
        $used = $usedSecCalcs->get($secModel->id);
        if ($used) {
            return 'data-snapshot-url="' . e(route('production.work-centers.sections.snapshots.modal', [$workCenter->id, $secModel->id, $used->id])) . '"';
        }
        return 'data-bs-toggle="modal" data-bs-target="#sectionCalculationModal_' . $secModel->id . '"';
    };
    $secDrillLabel = fn($secModel) => ($u = $usedSecCalcs->get($secModel->id)) ? ' Snapshot #' . ($u->snapshot_number ?? $u->id) : ' (latest snapshot)';

    if (!$wcTimelineMatrix) {
        $allShiftsMap = [];
        foreach ($activeSecModels as $sec) {
            $sCalc = $secCalcFor($sec);
            $sShifts = $sCalc?->calculation_details['operating_timeline']['shifts'] 
                ?? $sCalc?->calculation_details['operating_hours']['shifts'] 
                ?? [];
            if (empty($sShifts) && $isLatest) {
                foreach ($sec->shifts as $sh) {
                    if ($sh->isActive() && (bool) ($sh->pivot->active ?? true)) {
                        $sM = (int) substr($sh->start_time, 0, 2) * 60 + (int) substr($sh->start_time, 3, 2);
                        $eM = (int) substr($sh->end_time, 0, 2) * 60 + (int) substr($sh->end_time, 3, 2);
                        $durM = $eM >= $sM ? ($eM - $sM) : (1440 - $sM + $eM);
                        $sShifts[] = [
                            'id'             => $sh->id,
                            'name'           => $sh->name,
                            'code'           => $sh->code,
                            'start_time'     => $sh->start_time,
                            'end_time'       => $sh->end_time,
                            'start_minute'   => $sM,
                            'end_minute'     => $eM,
                            'duration_hours' => round($durM / 60, 2),
                            'is_overnight'   => $eM < $sM,
                        ];
                    }
                }
            }
            foreach ($sShifts as $sh) {
                $allShiftsMap[$sh['id']] = $sh;
            }
        }

        $mShifts = array_values($allShiftsMap);
        usort($mShifts, fn($a, $b) => ($a['start_minute'] ?? 0) <=> ($b['start_minute'] ?? 0));

        $mRows = [];
        foreach ($mShifts as $sh) {
            $shId = $sh['id'];
            $durHrs = (float) ($sh['duration_hours'] ?? 0);
            $combOut = 0.0;
            $combCost = 0.0;
            $activeCount = 0;
            $cells = [];

            foreach ($wcSectionsBreakdown as $secB) {
                $secId = $secB['section_id'] ?? null;
                $actualSec = $activeSecModels->firstWhere('id', $secId);
                $sCalc = $secCalcFor($actualSec);
                $secShifts = $sCalc?->calculation_details['operating_timeline']['shifts']
                    ?? $sCalc?->calculation_details['operating_hours']['shifts']
                    ?? [];
                $hasSh = !empty($secShifts)
                    ? collect($secShifts)->contains('id', $shId)
                    : ($isLatest && $actualSec ? $actualSec->shifts->contains('id', $shId) : false);

                if ($hasSh) {
                    $hourlyCap = (float) ($secB['capacity_per_hour'] ?? $sCalc?->capacity_per_hour ?? 0);
                    $uniqH = max(0.1, (float) ($secB['unique_operating_hours'] ?? $sCalc?->unique_operating_hours ?? 0));
                    $hourlyCost = (float) (($secB['total_daily_operating_cost'] ?? $sCalc?->total_daily_operating_cost ?? 0) / $uniqH);
                    $sOut = round($hourlyCap * $durHrs, 1);
                    $sCost = round($hourlyCost * $durHrs, 2);
                    $uCost = cost_per_unit_value($sCost, $sOut);

                    $cells[$secId] = [
                        'is_active'    => true,
                        'shift_output' => $sOut,
                        'shift_cost'   => $sCost,
                        'unit_cost'    => $uCost,
                    ];
                    $combOut += $sOut;
                    $combCost += $sCost;
                    $activeCount++;
                } else {
                    $cells[$secId] = [
                        'is_active' => false,
                    ];
                }
            }

            $mRows[] = [
                'shift'                 => $sh,
                'sections'              => $cells,
                'combined_output'       => $combOut,
                'combined_cost'         => $combCost,
                'active_sections_count' => $activeCount,
            ];
        }

        $wcTimelineMatrix = [
            'shifts' => $mShifts,
            'matrix' => $mRows,
        ];
    }

    $matrixShifts = $wcTimelineMatrix['shifts'] ?? [];
    $matrixRows   = $wcTimelineMatrix['matrix'] ?? [];

    // Build Gantt tracks per section
    $ganttTracks = [];
    foreach ($wcSectionsBreakdown as $secB) {
        $secId = $secB['section_id'] ?? null;
        $actualSec = $activeSecModels->firstWhere('id', $secId);
        $sCalc = $secCalcFor($actualSec);
        $sShifts = $sCalc?->calculation_details['operating_timeline']['shifts']
            ?? $sCalc?->calculation_details['operating_hours']['shifts']
            ?? [];
        if (empty($sShifts) && $actualSec && $isLatest) {
            foreach ($actualSec->shifts as $sh) {
                $sM = (int) substr($sh->start_time, 0, 2) * 60 + (int) substr($sh->start_time, 3, 2);
                $eM = (int) substr($sh->end_time, 0, 2) * 60 + (int) substr($sh->end_time, 3, 2);
                $durM = $eM >= $sM ? ($eM - $sM) : (1440 - $sM + $eM);
                $sShifts[] = [
                    'id'             => $sh->id,
                    'name'           => $sh->name,
                    'code'           => $sh->code,
                    'start_time'     => $sh->start_time,
                    'end_time'       => $sh->end_time,
                    'start_minute'   => $sM,
                    'end_minute'     => $eM,
                    'duration_hours' => round($durM / 60, 2),
                    'is_overnight'   => $eM < $sM,
                ];
            }
        } else {
            foreach ($sShifts as &$shItem) {
                if (empty($shItem['code']) && $actualSec) {
                    $matchSh = $actualSec->shifts->firstWhere('id', $shItem['id'] ?? null);
                    if ($matchSh) {
                        $shItem['code'] = $matchSh->code;
                    }
                }
            }
            unset($shItem);
        }
        $ganttTracks[$secId] = $sShifts;
    }

    $wcPeakHourlyCap = 0.0;
    foreach ($matrixRows as $r) {
        $dur = (float) ($r['shift']['duration_hours'] ?? 0);
        if ($dur > 0) {
            $rate = $r['combined_output'] / $dur;
            if ($rate > $wcPeakHourlyCap) {
                $wcPeakHourlyCap = $rate;
            }
        }
    }
    if ($wcPeakHourlyCap <= 0) {
        $wcPeakHourlyCap = $wcCapPerHour;
    }

    if ($wcUniqOperatingHours >= 24) {
        $dayShifts = array_filter($matrixShifts, fn($s) => ((int) ($s['start_minute'] ?? 0) >= 300) && ((int) ($s['start_minute'] ?? 0) < 720));
        $dayStart = !empty($dayShifts) ? min(array_column($dayShifts, 'start_time')) : '06:00';
        $wStart = substr($dayStart, 0, 5);
        $wcOpWindowStr = "{$wStart} – {$wStart}";
    } else {
        $coveredM = array_fill(0, 1440, false);
        foreach ($matrixShifts as $s) {
            $sM = (int) ($s['start_minute'] ?? 0);
            $eM = (int) ($s['end_minute'] ?? 0);
            $isO = (bool) ($s['is_overnight'] ?? false);
            for ($m = 0; $m < 1440; $m++) {
                if ($isO ? ($m >= $sM || $m < $eM) : ($m >= $sM && $m < $eM)) {
                    $coveredM[$m] = true;
                }
            }
        }
        if (count(array_filter($coveredM)) >= 1440) {
            $wcOpWindowStr = '06:00 – 06:00';
        } else {
            $earliestAct = null;
            $latestAct = null;
            for ($m = 0; $m < 1440; $m++) {
                if ($coveredM[$m]) {
                    if ($earliestAct === null) $earliestAct = $m;
                    $latestAct = $m;
                }
            }
            if ($earliestAct !== null) {
                $startStr = sprintf('%02d:%02d', floor($earliestAct / 60), $earliestAct % 60);
                $endStr = sprintf('%02d:%02d', floor(($latestAct + 1) / 60), ($latestAct + 1) % 60);
                $wcOpWindowStr = "{$startStr} – {$endStr}";
            } else {
                $wcOpWindowStr = 'None';
            }
        }
    }
@endphp

<div class="modal fade" id="{{ $modalElementId }}" tabindex="-1" aria-labelledby="{{ $modalElementId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="feather-pie-chart"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="{{ $modalElementId }}Label">
                                {{ __('production.wc_calculation_breakdown') }} &mdash; <span class="font-monospace text-primary">{{ $workCenter->code }}</span> ({{ $workCenter->name }})
                            </h5>
                            @if($wcSnapshot)
                                <span class="badge {{ $isLatest ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }} font-monospace fs-11">Snapshot #{{ $wcSnapshot->snapshot_number ?? $wcSnapshot->id }}</span>
                            @endif
                        </div>
                        <small class="text-muted fs-11">
                            Snapshot Recorded: {{ $wcSnapshot ? $wcSnapshot->created_at->format('Y-m-d H:i:s') : 'None yet' }}
                            &bull; Unique Operating Hours: <span class="font-monospace fw-semibold text-dark">{{ format_hours($wcUniqOperatingHours) }}</span>
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                @if(!$isLatest)
                    <div class="alert alert-soft-warning border border-warning-subtle d-flex align-items-center gap-2 mb-3 py-2 px-3 fs-12 snapshot-not-latest-banner">
                        <i class="feather-clock text-warning fs-14 flex-shrink-0"></i>
                        <span>Viewing snapshot <strong>#{{ $wcSnapshot->snapshot_number ?? $wcSnapshot->id }}</strong> from {{ $wcSnapshot->created_at?->format('Y-m-d H:i:s') }} &mdash; not the latest (latest is <strong>#{{ $latestWcSnapshot?->snapshot_number ?? $latestWcSnapshot?->id ?? '—' }}</strong>). Figures are exactly as stored in this snapshot.</span>
                    </div>
                @endif
                <!-- Top 6 KPI Summary Cards (3x2 Grid) -->
                <div class="row g-3 mb-4">
                    <!-- 1. Daily Capacity -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-activity me-1 text-success"></i>Daily Capacity
                                </span>
                                <span class="badge bg-soft-success text-success font-monospace fs-10">Output</span>
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {{ number_format($wcDailyCap, 1) }}
                                <span class="fs-11 fw-normal text-muted">units/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Avg: <strong class="text-dark">{{ number_format($wcCapPerHour, 1) }} u/h</strong></span>
                                <span>Peak: <strong class="text-primary">{{ number_format($wcPeakHourlyCap, 1) }} u/h</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Daily Operating Cost -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-dollar-sign me-1 text-warning"></i>Daily Operating Cost
                                </span>
                                <span class="badge bg-soft-warning text-warning font-monospace fs-10">Opex</span>
                            </div>
                            <div class="fs-22 fw-bolder text-primary font-monospace">
                                {{ format_currency($wcTotalDailyCost) }}
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Avg: <strong class="text-dark">{{ format_currency($wcTotalCostPerHour) }}/hr</strong></span>
                                <span>Window: <strong class="text-muted">{{ format_hours($wcUniqOperatingHours) }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Cost Per Unit -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-package me-1 text-info"></i>Cost Per Unit
                                </span>
                                <span class="badge bg-soft-info text-info font-monospace fs-10">Efficiency</span>
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {!! format_cost_per_unit($wcCostPerUnit, $wcTotalDailyCost, '') !!}
                                @if($wcCostPerUnit !== null)<span class="fs-11 fw-normal text-muted">/ unit</span>@endif
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Sections: <strong class="text-dark">{!! format_cost_per_unit($wcSecCostPerUnit, $totalSectionsDailyCost, '') !!}</strong></span>
                                <span>Burden: <strong class="text-primary">+{!! format_cost_per_unit($stationFixedBurdenPerUnit, null, '') !!}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Operating Hours -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-clock me-1 text-primary"></i>Operating Hours
                                </span>
                                @if($wcUniqOperatingHours >= 24)
                                    <span class="badge bg-soft-success text-success font-monospace fs-10">Full 24/7</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary font-monospace fs-10">{{ count($matrixShifts) }} Shifts</span>
                                @endif
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {{ format_hours($wcUniqOperatingHours, false) }}
                                <span class="fs-11 fw-normal text-muted">hrs/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Window: <strong class="text-dark">{{ $wcOpWindowStr }}</strong></span>
                                <span>Active: <strong class="text-primary">{{ count($wcSectionsBreakdown) }} Sections</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Machinery Expense -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-cpu me-1 text-secondary"></i>Machinery Expense
                                </span>
                                <span class="badge bg-soft-secondary text-secondary font-monospace fs-10">Asset Opex</span>
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {{ format_currency($wcDailyMachCost) }}
                                <span class="fs-11 fw-normal text-muted">/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Rate: <strong class="text-dark">{{ format_currency($wcMachCostPerHour) }}/hr</strong></span>
                                <span>Share: <strong class="text-muted">{{ $wcTotalDailyCost > 0 ? number_format(($wcDailyMachCost / $wcTotalDailyCost) * 100, 1) : '0.0' }}%</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Total Labor Cost -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-users me-1 text-info"></i>Total Labor Cost
                                </span>
                                <span class="badge bg-soft-info text-info font-monospace fs-10">Blended Labor</span>
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {{ format_currency($wcDailyLaborCost) }}
                                <span class="fs-11 fw-normal text-muted">/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Sections: <strong class="text-dark">{{ format_currency($totalSectionDailyLabor) }}</strong></span>
                                <span>Direct: <strong class="text-primary">{{ format_currency($wcDirectStaffDaily) }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs: Timeline Intervals, Sections Rollup, Direct Staff & Overhead, Activity Logs, Audit Metrics -->
                <ul class="nav nav-tabs nav-tabs-bottom mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                id="wc-intervals-tab" data-bs-toggle="tab" data-bs-target="#wc-intervals-pane" 
                                type="button" role="tab" aria-controls="wc-intervals-pane" aria-selected="true">
                            <i class="feather-clock text-warning fs-14"></i>
                            Timeline Matrix ({{ count($matrixShifts) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                id="wc-sections-tab" data-bs-toggle="tab" data-bs-target="#wc-sections-pane" 
                                type="button" role="tab" aria-controls="wc-sections-pane" aria-selected="false">
                            <i class="feather-grid text-primary fs-14"></i>
                            Contributing Sections ({{ count($wcSectionsBreakdown) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                id="wc-direct-tab" data-bs-toggle="tab" data-bs-target="#wc-direct-pane" 
                                type="button" role="tab" aria-controls="wc-direct-pane" aria-selected="false">
                            <i class="feather-users text-info fs-14"></i>
                            Direct Personnel & Overhead ({{ count($wcDirectStaff) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                id="wc-logs-tab" data-bs-toggle="tab" data-bs-target="#wc-logs-pane" 
                                type="button" role="tab" aria-controls="wc-logs-pane" aria-selected="false">
                            <i class="feather-activity text-secondary fs-14"></i>
                            Activity & Audit Logs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                id="wc-audit-tab" data-bs-toggle="tab" data-bs-target="#wc-audit-pane" 
                                type="button" role="tab" aria-controls="wc-audit-pane" aria-selected="false">
                            <i class="feather-check-circle text-success fs-14"></i>
                            Audit Metrics (47)
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- TAB 1: 24-HOUR GANTT & SHIFT X SECTION OPERATING MATRIX -->
                    <div class="tab-pane fade show active" id="wc-intervals-pane" role="tabpanel" aria-labelledby="wc-intervals-tab">
                        
                        <!-- 1. 24-Hour Visual Coverage Bar (Mini-Gantt) -->
                        <div class="card border mb-3 shadow-none overflow-hidden">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark fs-12 mb-0">
                                    <i class="feather-bar-chart-2 me-1 text-primary"></i>24-Hour Visual Coverage Bar (00:00 &ndash; 24:00)
                                </h6>
                                <div class="d-flex align-items-center gap-3 fs-11">
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="rounded d-inline-block" style="width: 12px; height: 10px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);"></span> Morning
                                    </span>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="rounded d-inline-block" style="width: 12px; height: 10px; background: linear-gradient(135deg, #6366f1, #4338ca);"></span> Evening
                                    </span>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="rounded d-inline-block" style="width: 12px; height: 10px; background: linear-gradient(135deg, #8b5cf6, #6d28d9);"></span> Night
                                    </span>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="rounded d-inline-block" style="width: 12px; height: 10px; background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 3px, #cbd5e1 3px, #cbd5e1 6px); border: 1px solid #94a3b8;"></span> Off-Hours (Idle)
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-3 bg-white">
                                <!-- Time Axis Markers -->
                                <div class="d-flex justify-content-between px-1 mb-2 font-monospace fs-10 text-muted border-bottom pb-1">
                                    <span>00:00</span>
                                    <span>03:00</span>
                                    <span>06:00</span>
                                    <span>09:00</span>
                                    <span>12:00</span>
                                    <span>15:00</span>
                                    <span>18:00</span>
                                    <span>21:00</span>
                                    <span>24:00</span>
                                </div>

                                <!-- Tracks for Each Section -->
                                <div class="d-flex flex-column gap-3">
                                    @forelse($wcSectionsBreakdown as $secB)
                                        @php
                                            $secId = $secB['section_id'] ?? null;
                                            $secCode = $secB['section_code'] ?? $secB['code'] ?? '—';
                                            $secName = $secB['section_name'] ?? $secB['name'] ?? '—';
                                            $secHours = (float) ($secB['unique_operating_hours'] ?? 0);
                                            $secTrackShifts = $ganttTracks[$secId] ?? [];
                                        @endphp
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center fs-11 mb-1">
                                                <span class="fw-bold text-dark">
                                                    <span class="font-monospace text-primary fw-semibold">[{{ $secCode }}]</span> {{ $secName }}
                                                </span>
                                                <span class="font-monospace fs-11 {{ $secHours >= 24 ? 'text-success fw-bold' : 'text-primary' }}">
                                                    {{ format_hours($secHours) }} ({{ count($secTrackShifts) }} Shifts)
                                                </span>
                                            </div>
                                            <div class="w-100 rounded position-relative overflow-hidden border" 
                                                 style="height: 28px; background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 8px, #edf2f7 8px, #edf2f7 16px); border-color: #cbd5e1 !important; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                                                <!-- Hour Grid Overlay Lines -->
                                                <div style="position: absolute; top: 0; bottom: 0; left: 12.5%; width: 1px; border-left: 1px dashed rgba(100, 116, 139, 0.25); z-index: 1; pointer-events: none;"></div>
                                                <div style="position: absolute; top: 0; bottom: 0; left: 25.0%; width: 1px; border-left: 1px dashed rgba(100, 116, 139, 0.25); z-index: 1; pointer-events: none;"></div>
                                                <div style="position: absolute; top: 0; bottom: 0; left: 37.5%; width: 1px; border-left: 1px dashed rgba(100, 116, 139, 0.25); z-index: 1; pointer-events: none;"></div>
                                                <div style="position: absolute; top: 0; bottom: 0; left: 50.0%; width: 1px; border-left: 1px dashed rgba(100, 116, 139, 0.25); z-index: 1; pointer-events: none;"></div>
                                                <div style="position: absolute; top: 0; bottom: 0; left: 62.5%; width: 1px; border-left: 1px dashed rgba(100, 116, 139, 0.25); z-index: 1; pointer-events: none;"></div>
                                                <div style="position: absolute; top: 0; bottom: 0; left: 75.0%; width: 1px; border-left: 1px dashed rgba(100, 116, 139, 0.25); z-index: 1; pointer-events: none;"></div>
                                                <div style="position: absolute; top: 0; bottom: 0; left: 87.5%; width: 1px; border-left: 1px dashed rgba(100, 116, 139, 0.25); z-index: 1; pointer-events: none;"></div>

                                                @foreach($secTrackShifts as $tSh)
                                                    @php
                                                        $sM = (int) ($tSh['start_minute'] ?? 0);
                                                        $eM = (int) ($tSh['end_minute'] ?? 0);
                                                        $isOver = (bool) ($tSh['is_overnight'] ?? false);
                                                        $shCode = !empty($tSh['code']) ? $tSh['code'] : (!empty($tSh['name']) ? strtoupper(substr($tSh['name'], 0, 4)) : 'SH');
                                                        $shTitle = ($tSh['name'] ?? 'Shift') . ' (' . $shCode . ') • ' . substr($tSh['start_time'] ?? '', 0, 5) . ' – ' . substr($tSh['end_time'] ?? '', 0, 5) . (!empty($tSh['duration_hours']) ? ' • ' . format_hours($tSh['duration_hours']) : '');

                                                        $isNight = $isOver || ($sM >= 1260) || ($sM < 300);
                                                        $isMorning = ($sM >= 300) && ($sM < 720);
                                                        $barGradient = $isNight 
                                                            ? 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)' 
                                                            : ($isMorning 
                                                                ? 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)' 
                                                                : 'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)');
                                                    @endphp
                                                    @if($isOver)
                                                        <div class="position-absolute d-flex align-items-center justify-content-center text-truncate px-1"
                                                             style="top: 1px; bottom: 1px; z-index: 2; left: {{ round(($sM / 1440) * 100, 2) }}%; width: {{ round(((1440 - $sM) / 1440) * 100, 2) }}%; background: {{ $barGradient }}; border-radius: 4px; color: #ffffff; font-family: var(--bs-font-monospace); font-size: 10.5px; font-weight: 700; letter-spacing: 0.5px; border-left: 1px solid rgba(255,255,255,0.4); border-right: 1px solid rgba(255,255,255,0.4); box-shadow: 0 1px 3px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.25); cursor: pointer;"
                                                             title="{{ $shTitle }}" data-bs-toggle="tooltip">
                                                            {{ $shCode }}
                                                        </div>
                                                        <div class="position-absolute d-flex align-items-center justify-content-center text-truncate px-1"
                                                             style="top: 1px; bottom: 1px; z-index: 2; left: 0%; width: {{ round(($eM / 1440) * 100, 2) }}%; background: {{ $barGradient }}; border-radius: 4px; color: #ffffff; font-family: var(--bs-font-monospace); font-size: 10.5px; font-weight: 700; letter-spacing: 0.5px; border-left: 1px solid rgba(255,255,255,0.4); border-right: 1px solid rgba(255,255,255,0.4); box-shadow: 0 1px 3px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.25); cursor: pointer;"
                                                             title="{{ $shTitle }}" data-bs-toggle="tooltip">
                                                            {{ $shCode }}
                                                        </div>
                                                    @else
                                                        <div class="position-absolute d-flex align-items-center justify-content-center text-truncate px-1"
                                                             style="top: 1px; bottom: 1px; z-index: 2; left: {{ round(($sM / 1440) * 100, 2) }}%; width: {{ round((($eM - $sM) / 1440) * 100, 2) }}%; background: {{ $barGradient }}; border-radius: 4px; color: #ffffff; font-family: var(--bs-font-monospace); font-size: 10.5px; font-weight: 700; letter-spacing: 0.5px; border-left: 1px solid rgba(255,255,255,0.4); border-right: 1px solid rgba(255,255,255,0.4); box-shadow: 0 1px 3px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.25); cursor: pointer;"
                                                             title="{{ $shTitle }}" data-bs-toggle="tooltip">
                                                            {{ $shCode }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center text-muted py-2 fst-italic fs-11">No active sections contributing to timeline coverage.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <!-- 2. Shift x Section Operating Matrix Table -->
                        <div class="card border mb-3 shadow-none overflow-hidden">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark fs-12 mb-0">
                                    <i class="feather-grid me-1 text-primary"></i>Shift &times; Section Production &amp; Cost Matrix
                                </h6>
                                <span class="text-muted fs-11">Shifts as Rows &bull; Sections as Column Headers</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0 fs-12">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 18%;" class="bg-light py-2 px-2.5">
                                                <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Shift Code</span>
                                                <span class="text-dark fw-bold fs-11">Window &bull; Duration</span>
                                            </th>
                                            @foreach($wcSectionsBreakdown as $secB)
                                                @php
                                                    $sCode = $secB['section_code'] ?? $secB['code'] ?? '—';
                                                    $sName = $secB['section_name'] ?? $secB['name'] ?? 'Section';
                                                @endphp
                                                <th class="text-center py-2 px-2" style="width: {{ count($wcSectionsBreakdown) > 0 ? round(63 / count($wcSectionsBreakdown), 1) : 63 }}%;">
                                                    <span class="badge bg-soft-primary text-primary font-monospace fw-bold fs-12 px-2.5 py-1" 
                                                          title="{{ $sName }}" 
                                                          data-bs-toggle="tooltip">
                                                        [{{ $sCode }}]
                                                    </span>
                                                </th>
                                            @endforeach
                                            <th class="text-end bg-light py-2 px-2.5" style="width: 19%;">
                                                <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Combined Shift</span>
                                                <span class="text-primary fw-bold fs-11">Total Output &amp; Cost</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($matrixRows as $row)
                                            @php
                                                $sh = $row['shift'];
                                                $isNight = !empty($sh['is_overnight']) || ((int) ($sh['start_minute'] ?? 0) >= 1260) || ((int) ($sh['start_minute'] ?? 0) < 300);
                                                $isMorning = ((int) ($sh['start_minute'] ?? 0) >= 300) && ((int) ($sh['start_minute'] ?? 0) < 720);
                                                $shIcon = $isNight ? '🌙' : ($isMorning ? '☀️' : '🌇');
                                                $rowShCode = !empty($sh['code']) ? $sh['code'] : (!empty($sh['name']) ? strtoupper(substr($sh['name'], 0, 4)) : 'SHIFT');
                                            @endphp
                                            <tr>
                                                <!-- Shift Info Cell (Code Only) -->
                                                <td class="bg-light py-2 px-2.5">
                                                    <div class="d-flex align-items-center gap-1.5">
                                                        <span class="fs-13">{{ $shIcon }}</span>
                                                        <span class="badge bg-soft-dark text-dark font-monospace fw-bold fs-11 px-2 py-0.5" 
                                                              title="{{ $sh['name'] ?? 'Shift' }}" 
                                                              data-bs-toggle="tooltip">
                                                            {{ $rowShCode }}
                                                        </span>
                                                    </div>
                                                    <div class="font-monospace text-dark fs-11 mt-1">
                                                        {{ substr($sh['start_time'] ?? '', 0, 5) }} &ndash; {{ substr($sh['end_time'] ?? '', 0, 5) }}
                                                        <span class="text-muted fs-10">({{ format_hours($sh['duration_hours'] ?? 0) }})</span>
                                                    </div>
                                                </td>

                                                <!-- Section Cells -->
                                                @foreach($wcSectionsBreakdown as $secB)
                                                    @php
                                                        $secId = $secB['section_id'] ?? null;
                                                        $cell = $row['sections'][$secId] ?? ['is_active' => false];
                                                    @endphp
                                                    @if(!empty($cell['is_active']))
                                                        <td class="p-2.5">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                @if($row['active_sections_count'] === 1)
                                                                    <span class="badge bg-soft-primary text-primary fs-10 px-1.5 py-0.5">Solo Running</span>
                                                                @else
                                                                    <span class="badge bg-soft-success text-success fs-10 px-1.5 py-0.5">Active</span>
                                                                @endif
                                                                <span class="font-monospace fw-bold text-success fs-11">{{ number_format($cell['shift_output'] ?? 0, 1) }} u</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between align-items-center fs-10 font-monospace text-muted">
                                                                <span>Cost: <strong class="text-dark">{{ format_currency($cell['shift_cost'] ?? 0) }}</strong></span>
                                                                <span>{!! format_cost_per_unit(array_key_exists("unit_cost", $cell) ? $cell["unit_cost"] : 0.0, $cell["shift_cost"] ?? null, "/u") !!}</span>
                                                            </div>
                                                        </td>
                                                    @else
                                                        <td class="p-2.5 text-center bg-soft-light text-muted">
                                                            <span class="badge bg-light text-muted border border-dashed fs-9 px-1.5 py-0.5">Off-Hours (Idle)</span>
                                                            <small class="text-muted d-block fs-10 mt-0.5">0 output &bull; {{ format_currency(0) }} cost</small>
                                                        </td>
                                                    @endif
                                                @endforeach

                                                <!-- Shift Combined Cell -->
                                                <td class="text-end font-monospace p-2.5 bg-light">
                                                    <span class="fw-bold text-success d-block fs-11">{{ number_format($row['combined_output'] ?? 0, 1) }} units</span>
                                                    <span class="fw-bold text-dark d-block fs-11">{{ format_currency($row['combined_cost'] ?? 0) }}</span>
                                                    <small class="badge bg-soft-info text-info fs-9">{{ $row['active_sections_count'] }} of {{ count($wcSectionsBreakdown) }} active</small>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ count($wcSectionsBreakdown) + 2 }}" class="text-center text-muted py-3 fst-italic">
                                                    No active shifts mapped to sections.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if(!empty($matrixRows))
                                        <tfoot class="table-light border-top-2 fw-bold fs-11">
                                            <tr>
                                                <td class="text-uppercase text-dark">Section Daily Totals:</td>
                                                @foreach($wcSectionsBreakdown as $secB)
                                                    @php
                                                        $sCap = (float) ($secB['daily_capacity'] ?? 0);
                                                        $sCost = (float) ($secB['total_daily_operating_cost'] ?? (($secB['total_daily_labor_cost'] ?? 0) + ($secB['total_daily_machine_cost'] ?? 0)));
                                                        $sUnit = cost_per_unit_value($sCost, $sCap);
                                                    @endphp
                                                    <td class="text-center font-monospace p-2">
                                                        <span class="text-success fw-bold d-block">{{ number_format($sCap, 1) }} units</span>
                                                        <span class="text-dark d-block">{{ format_currency($sCost) }} / day</span>
                                                        <span class="badge bg-soft-success text-success fs-9">{!! format_cost_per_unit($sUnit, $sCost, " / u") !!}</span>
                                                    </td>
                                                @endforeach
                                                <td class="text-end font-monospace p-2">
                                                    <span class="text-success fw-bolder d-block">{{ number_format($wcDailyCap, 1) }} units</span>
                                                    <span class="text-dark fw-bolder d-block">{{ format_currency($totalSectionsDailyCost) }}</span>
                                                    <small class="text-muted fs-10">Sections Sum</small>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- TAB 2: CONTRIBUTING SECTIONS BREAKDOWN -->
                    <div class="tab-pane fade" id="wc-sections-pane" role="tabpanel" aria-labelledby="wc-sections-tab">
                        <!-- 1. Contributing Sections Labor & Expense Reconciliation (Summary) -->
                        <div class="card border mb-3 shadow-none bg-light p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark fs-12">
                                    <i class="feather-check-square text-primary me-1"></i>Contributing Sections Labor &amp; Expense Reconciliation
                                </span>
                                <span class="font-monospace fw-bold text-primary fs-12">
                                    Work Center Grand Total: {{ format_currency($wcTotalDailyCost) }} ({!! format_cost_per_unit($wcCostPerUnit, $wcTotalDailyCost) !!})
                                </span>
                            </div>
                            <div class="row g-2 font-monospace fs-11">
                                <div class="col-md-3 col-sm-6">
                                    <div class="p-2 rounded bg-white border d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Section Labor:</span>
                                        <span class="fw-bold text-primary">{{ format_currency($totalSectionDailyLabor) }}</span>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="p-2 rounded bg-white border d-flex justify-content-between align-items-center">
                                        <span class="text-muted">+ Direct WC Staff:</span>
                                        <span class="fw-bold text-primary">{{ format_currency($wcDirectStaffDaily) }}</span>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="p-2 rounded bg-white border d-flex justify-content-between align-items-center">
                                        <span class="text-muted">= Total WC Labor:</span>
                                        <span class="fw-bold text-dark">{{ format_currency($wcDailyLaborCost) }}</span>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="p-2 rounded bg-white border d-flex justify-content-between align-items-center">
                                        <span class="text-muted">+ Mach &amp; Overhead:</span>
                                        <span class="fw-bold text-warning">{{ format_currency($totalSectionDailyMach + $wcOverheadDaily) }} <span class="fs-10 text-muted fw-normal">/ day</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Section Capacity & Expense Roll-Up (Details) -->
                        <div class="card border shadow-none overflow-hidden">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-grid me-1 text-primary"></i>Section Capacity &amp; Expense Roll-Up
                                    </h6>
                                    <span class="badge bg-soft-primary text-primary fs-10 font-monospace">{{ count($wcSectionsBreakdown) }} Sections Contributing</span>
                                </div>
                                <small class="text-muted fs-11 font-monospace">
                                    Unique WC Window: <strong class="text-dark">{{ format_hours($wcUniqOperatingHours) }}</strong>
                                </small>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered align-middle mb-0 fs-12">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 20%;">Section</th>
                                            <th style="width: 12%;">Runtime</th>
                                            <th style="width: 14%;" class="text-end">Capacity</th>
                                            <th style="width: 14%;" class="text-end">Labor Cost</th>
                                            <th style="width: 13%;" class="text-end">Machine Cost</th>
                                            <th style="width: 13%;" class="text-end">Total Cost</th>
                                            <th style="width: 10%;" class="text-end">Cost / Unit</th>
                                            <th style="width: 4%;" class="text-center">Audit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($wcSectionsBreakdown as $secB)
                                            @php
                                                $secId = $secB['section_id'] ?? null;
                                                $actualSec = $activeSecModels->firstWhere('id', $secId);
                                                $sCalc = $secCalcFor($actualSec);
                                                $sHours = (float) ($secB['unique_operating_hours'] ?? $secB['operating_hours'] ?? $sCalc?->unique_operating_hours ?? 0);
                                                $sCapDaily = (float) ($secB['daily_capacity'] ?? $sCalc?->daily_capacity ?? 0);
                                                $sCapHourly = (float) ($secB['capacity_per_hour'] ?? $sCalc?->capacity_per_hour ?? ($sHours > 0 ? $sCapDaily / $sHours : 0));
                                                
                                                $sLaborDaily = (float) ($secB['total_daily_labor_cost'] ?? $sCalc?->total_daily_labor_cost ?? 0);
                                                $sLaborHourly = (float) ($secB['labor_cost_per_hour'] ?? $sCalc?->labor_cost_per_hour ?? ($sHours > 0 ? $sLaborDaily / $sHours : 0));
                                                
                                                $sMachDaily = (float) ($secB['total_daily_machine_cost'] ?? $sCalc?->total_daily_machine_cost ?? 0);
                                                $sMachHourly = (float) ($secB['machine_cost_per_hour'] ?? $sCalc?->machine_cost_per_hour ?? ($sHours > 0 ? $sMachDaily / $sHours : 0));
                                                
                                                $sTotalDaily = (float) ($secB['total_daily_operating_cost'] ?? $sCalc?->total_daily_operating_cost ?? ($sLaborDaily + $sMachDaily));
                                                $sTotalHourly = $sHours > 0 ? $sTotalDaily / $sHours : 0;
                                                $sCostUnit = cost_per_unit_value($sTotalDaily, $sCapDaily);
                                                $sShiftCount = count($ganttTracks[$secId] ?? []);
                                            @endphp
                                            <tr>
                                                <!-- Section Code & Name -->
                                                <td class="p-2.5">
                                                    <span class="font-monospace text-primary fw-bold d-block">[{{ $secB['section_code'] ?? $secB['code'] ?? '—' }}]</span>
                                                    <span class="text-dark fw-semibold">{{ $secB['section_name'] ?? $secB['name'] ?? '—' }}</span>
                                                </td>

                                                <!-- Runtime -->
                                                <td class="p-2.5 font-monospace">
                                                    <span class="badge bg-soft-secondary text-secondary font-monospace fs-11">
                                                        {{ format_hours($sHours) }}
                                                    </span>
                                                    @if($sShiftCount > 0)
                                                        <small class="text-muted d-block fs-10 mt-0.5">{{ $sShiftCount }} {{ Str::plural('Shift', $sShiftCount) }}</small>
                                                    @endif
                                                </td>

                                                <!-- Capacity (Daily + Hourly) -->
                                                <td class="p-2.5 text-end font-monospace">
                                                    <span class="fw-bold text-success d-block fs-11">{{ number_format($sCapDaily, 1) }} u</span>
                                                    <small class="text-muted fs-10">Avg: <strong class="text-dark">{{ number_format($sCapHourly, 1) }} u/h</strong></small>
                                                </td>

                                                <!-- Labor Cost (Daily + Avg Labor Rate) -->
                                                <td class="p-2.5 text-end font-monospace">
                                                    <span class="fw-bold text-primary d-block fs-11">{{ format_currency($sLaborDaily) }}</span>
                                                    <small class="text-muted fs-10">Avg: <strong class="text-dark">{{ format_currency($sLaborHourly) }}/hr</strong></small>
                                                </td>

                                                <!-- Machine Cost (Daily + Hourly Rate) -->
                                                <td class="p-2.5 text-end font-monospace">
                                                    <span class="fw-bold text-warning d-block fs-11">{{ format_currency($sMachDaily) }}</span>
                                                    <small class="text-muted fs-10">Rate: <strong class="text-dark">{{ format_currency($sMachHourly) }}/hr</strong></small>
                                                </td>

                                                <!-- Total Operating Cost (Daily + Effective Rate) -->
                                                <td class="p-2.5 text-end font-monospace">
                                                    <span class="fw-bolder text-dark d-block fs-11">{{ format_currency($sTotalDaily) }}</span>
                                                    <small class="text-muted fs-10">Eff: <strong class="text-dark">{{ format_currency($sTotalHourly) }}/hr</strong></small>
                                                </td>

                                                <!-- Cost Per Unit Badge -->
                                                <td class="p-2.5 text-end font-monospace">
                                                    <span class="badge bg-soft-success text-success border border-success-subtle fs-11 px-2 py-0.5">
                                                        {!! format_cost_per_unit($sCostUnit, $sTotalDaily, " / u") !!}
                                                    </span>
                                                </td>

                                                <!-- Audit Drill-Down Action Button -->
                                                <td class="p-2.5 text-center">
                                                    @if($actualSec)
                                                        @php $usedSnap = $usedSecCalcs->get($actualSec->id); @endphp
                                                        <button type="button" 
                                                                class="btn btn-xs btn-outline-primary px-1.5 py-0.5 font-monospace fs-10" 
                                                                {!! $secDrillAttrs($actualSec) !!}
                                                                title="View 52 Audit Metrics for {{ $actualSec->name }} —{{ $secDrillLabel($actualSec) }}">
                                                            @if($usedSnap)#{{ $usedSnap->snapshot_number ?? $usedSnap->id }} @endif<i class="feather-external-link fs-11"></i>
                                                        </button>
                                                    @else
                                                        <span class="text-muted fs-10">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-3 fst-italic">
                                                    No sections contributing to this snapshot.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if(!empty($wcSectionsBreakdown))
                                        <tfoot class="table-light border-top-2 fw-bold fs-11">
                                            <tr>
                                                <td colspan="2" class="text-uppercase text-dark p-2.5">
                                                    All Contributing Sections Total:
                                                </td>
                                                <td class="text-end font-monospace p-2.5">
                                                    <span class="text-success fw-bold d-block">{{ number_format($wcDailyCap, 1) }} units</span>
                                                    <small class="text-muted fs-10">Avg: {{ number_format($wcCapPerHour, 1) }} u/h</small>
                                                </td>
                                                <td class="text-end font-monospace p-2.5">
                                                    <span class="text-primary fw-bold d-block">{{ format_currency($totalSectionDailyLabor) }}</span>
                                                    <small class="text-muted fs-10">Avg: {{ format_currency($wcUniqOperatingHours > 0 ? $totalSectionDailyLabor / $wcUniqOperatingHours : 0) }}/hr</small>
                                                </td>
                                                <td class="text-end font-monospace p-2.5">
                                                    <span class="text-warning fw-bold d-block">{{ format_currency($totalSectionDailyMach) }}</span>
                                                    <small class="text-muted fs-10">Rate: {{ format_currency($wcMachCostPerHour) }}/hr</small>
                                                </td>
                                                <td class="text-end font-monospace p-2.5">
                                                    <span class="text-dark fw-bolder d-block">{{ format_currency($totalSectionsDailyCost) }}</span>
                                                    <small class="text-muted fs-10">Eff: {{ format_currency($wcUniqOperatingHours > 0 ? $totalSectionsDailyCost / $wcUniqOperatingHours : 0) }}/hr</small>
                                                </td>
                                                <td class="text-end font-monospace p-2.5">
                                                    <span class="text-dark fw-bold d-block fs-11">
                                                        {!! format_cost_per_unit($wcSecCostPerUnit, $totalSectionsDailyCost, " / u") !!}
                                                    </span>
                                                    <small class="text-muted fs-9">Sections Avg</small>
                                                </td>
                                                <td class="text-center p-2.5 text-muted">—</td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: DIRECT STAFF & OVERHEAD -->
                    <div class="tab-pane fade" id="wc-direct-pane" role="tabpanel" aria-labelledby="wc-direct-tab">
                        <!-- 1. Direct Station Fixed Burden Callout (Summary) -->
                        <div class="card border mb-3 shadow-none bg-light p-3">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-soft-info text-info p-1.5 rounded-circle"><i class="feather-info fs-13"></i></span>
                                    <div>
                                        <strong class="text-dark fs-12">Direct Station Fixed Burden:</strong>
                                        <span class="text-muted fs-11 font-monospace ms-1">
                                            Direct Staff ({{ format_currency($wcDirectStaffDaily) }}) + Overhead ({{ format_currency($wcOverheadDaily) }} / day) = {{ format_currency($wcDirectStaffDaily + $wcOverheadDaily) }} / day
                                        </span>
                                    </div>
                                </div>
                                <span class="badge bg-soft-primary text-primary font-monospace fs-11 px-2.5 py-1">
                                    Adds {!! format_cost_per_unit($stationFixedBurdenPerUnit) !!} across {{ number_format($wcDailyCap, 1) }} units
                                </span>
                            </div>
                        </div>

                        <!-- 2. Direct Work Center Staff (Unassigned to any Section) (Details) -->
                        <div class="card border mb-3 shadow-none overflow-hidden">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-users me-1 text-info"></i>Direct Work Center Staff (Unassigned to any Section)
                                    </h6>
                                    <span class="badge bg-soft-info text-info font-monospace fs-11">{{ count($wcDirectStaff) }} Assigned Employees</span>
                                </div>
                                <span class="badge bg-soft-primary text-primary font-monospace fs-11">Total Daily Allocated: {{ format_currency($wcDirectStaffDaily) }}</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered align-middle mb-0 fs-12">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 28%">Employee Name &amp; Code</th>
                                            <th style="width: 22%">Role / Designation</th>
                                            <th style="width: 16%" class="text-end">Monthly HRMS CTC</th>
                                            <th style="width: 16%" class="text-center">WC Allocation</th>
                                            <th style="width: 18%" class="text-end">Daily Allocated Salary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($wcDirectStaff as $dst)
                                            @php
                                                $mCTC = (float) ($dst['monthly_salary'] ?? $dst['monthly_ctc'] ?? $dst['hrms_net_monthly_salary'] ?? 0);
                                                $baseDaily = (float) ($dst['base_daily_salary'] ?? $mCTC / 30);
                                                $activeWcCount = (int) ($dst['active_wc_count'] ?? $dst['total_active_work_centers'] ?? 1);
                                                $allocPct = (float) ($dst['allocation_percentage'] ?? ($activeWcCount > 0 ? round(100 / $activeWcCount, 1) : 100));
                                                $dCost = (float) ($dst['daily_cost'] ?? $dst['daily_salary'] ?? 0);
                                                $hRate = (float) ($dst['hourly_rate'] ?? 0);
                                            @endphp
                                            <tr>
                                                <td class="p-2.5">
                                                    <div class="d-flex align-items-center gap-1.5">
                                                        <i class="feather-user me-1 text-muted"></i>
                                                        <span class="fw-semibold text-dark">{{ $dst['name'] ?? '—' }}</span>
                                                        @if(!empty($dst['employee_code']))
                                                            <span class="badge bg-soft-dark text-dark font-monospace fs-10 ms-1">{{ $dst['employee_code'] }}</span>
                                                        @endif
                                                    </div>
                                                    <small class="text-muted fs-10 d-block mt-0.5">
                                                        {{ $activeWcCount > 1 ? "Assigned across {$activeWcCount} Work Centers" : 'Dedicated to this Work Center' }}
                                                    </small>
                                                </td>
                                                <td class="p-2.5 text-dark">
                                                    {{ $dst['role'] ?? 'Floating Station Personnel' }}
                                                </td>
                                                <td class="p-2.5 text-end font-monospace">
                                                    <span class="fw-semibold text-dark d-block fs-11">{{ format_currency($mCTC) }}</span>
                                                    <small class="text-muted fs-10">Base: {{ format_currency($baseDaily) }}/d</small>
                                                </td>
                                                <td class="p-2.5 text-center">
                                                    @if($allocPct < 100)
                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle font-monospace fs-10 px-2 py-0.5">
                                                            {{ $allocPct }}% (1 of {{ $activeWcCount }} WCs)
                                                        </span>
                                                    @else
                                                        <span class="badge bg-soft-success text-success border border-success-subtle font-monospace fs-10 px-2 py-0.5">
                                                            100% Dedicated
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="p-2.5 text-end font-monospace">
                                                    <span class="fw-bold text-primary d-block fs-11">{{ format_currency($dCost) }} / day
                                                        <i class="feather-info text-muted ms-1 cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ \App\Domains\Production\Support\CalculationText::directStaffFormula($dst) }}"></i>
                                                    </span>
                                                    @if($hRate > 0)
                                                        <small class="text-muted fs-10">({{ format_currency($hRate) }} / hr)</small>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3 fst-italic">
                                                    No direct station employees assigned. All labor is sourced from section-level rosters.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if(!empty($wcDirectStaff))
                                        <tfoot class="table-light border-top-2 fw-bold fs-11">
                                            <tr>
                                                <td colspan="2" class="text-uppercase text-dark p-2.5">
                                                    Total Direct Work Center Staff ({{ count($wcDirectStaff) }} Employees):
                                                </td>
                                                <td class="text-end font-monospace text-dark p-2.5">
                                                    {{ format_currency($totalDirectMonthlyCTC) }} / mo
                                                </td>
                                                <td class="text-center text-muted fs-10 font-monospace p-2.5">
                                                    Effective ~{{ $totalDirectMonthlyCTC > 0 ? round(($wcDirectStaffDaily / max(1, $totalDirectMonthlyCTC / 30)) * 100, 1) : 100 }}%
                                                </td>
                                                <td class="text-end font-monospace p-2.5">
                                                    <span class="fw-bolder text-primary d-block fs-11">{{ format_currency($wcDirectStaffDaily) }} / day</span>
                                                    <small class="text-muted fs-10">({{ format_currency($wcUniqOperatingHours > 0 ? $wcDirectStaffDaily / $wcUniqOperatingHours : 0) }} / hr over {{ format_hours($wcUniqOperatingHours) }} operating window)</small>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <!-- 3. Work Center Flat Overhead Integration -->
                        <div class="card border shadow-none">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark fs-12 mb-0">
                                    <i class="feather-home me-1 text-secondary"></i>Work Center Flat Overhead Integration
                                </h6>
                                <span class="text-muted fs-11 font-monospace">Fixed Daily Facility Expense</span>
                            </div>
                            <div class="card-body p-3 fs-12 bg-white">
                                <p class="text-muted mb-2 fs-11">
                                    Facility overhead is configured directly on the Work Center as a flat daily cost. It is aggregated into the total daily operational cost and distributed across active operational hours.
                                </p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded bg-light">
                                            <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Configured Daily Overhead</span>
                                            <span class="fs-16 fw-bold font-monospace text-dark">{{ format_currency($wcOverheadDaily) }} / day</span>
                                            <small class="text-muted fs-10 d-block mt-0.5">Fixed facility operational cost</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded bg-light">
                                            <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Unique Operating Hours</span>
                                            <span class="fs-16 fw-bold font-monospace text-dark">{{ format_hours($wcUniqOperatingHours) }}</span>
                                            <small class="text-muted fs-10 d-block mt-0.5">Union clock window across sections</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded bg-light">
                                            <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Derived Hourly Rate</span>
                                            <span class="fs-16 fw-bold font-monospace text-primary">
                                                {{ $wcUniqOperatingHours > 0 ? format_currency($wcOverheadDaily / $wcUniqOperatingHours) : '—' }} / hr
                                            </span>
                                            <small class="text-muted fs-10 d-block mt-0.5">Daily Overhead &divide; Operating Hours</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: ACTIVITY & AUDIT LOGS -->
                    <div class="tab-pane fade" id="wc-logs-pane" role="tabpanel" aria-labelledby="wc-logs-tab">
                        <div class="card border shadow-none">
                            <div class="card-header bg-light py-2 px-3 border-bottom">
                                <h6 class="fw-bold text-dark fs-12 mb-0">
                                    <i class="feather-activity me-1 text-primary"></i>Audit Log Entries
                                </h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 16%">Timestamp</th>
                                            <th style="width: 15%">Calculation Level</th>
                                            <th style="width: 37%">Description</th>
                                            <th style="width: 12%">Batch Run</th>
                                            <th style="width: 12%">User</th>
                                            <th style="width: 8%" class="text-end">Snapshot</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($workCenter->activityLogs()->orderByDesc('id')->take(20)->get() as $log)
                                            <tr>
                                                <td class="font-monospace text-muted fs-11">
                                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                                    <small class="text-muted d-block fs-10">({{ $log->created_at->diffForHumans() }})</small>
                                                </td>
                                                <td>
                                                    @if($log->calculation_type === 'section')
                                                        <span class="badge bg-soft-success text-success font-monospace fs-10 text-uppercase px-2 py-0.5 rounded-pill">
                                                            <i class="feather-layers me-1"></i>Section Calc
                                                        </span>
                                                    @else
                                                        <span class="badge bg-soft-primary text-primary font-monospace fs-10 text-uppercase px-2 py-0.5 rounded-pill">
                                                            <i class="feather-box me-1"></i>WC Rollup
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-dark fw-medium">{{ $log->description }}</td>
                                                <td>
                                                    @if($log->batch_id)
                                                        <span class="badge bg-light text-muted font-monospace fs-10 border" title="Calculation Batch: {{ $log->batch_id }}">
                                                            <i class="feather-git-commit me-0.5 text-primary"></i>#{{ substr($log->batch_id, 0, 8) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted fs-10 fst-italic">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($log->user)
                                                        <span class="fw-semibold text-dark"><i class="feather-user me-1 text-muted fs-11"></i>{{ $log->user->name }}</span>
                                                    @else
                                                        <span class="text-muted fst-italic"><i class="feather-cpu me-1 fs-11"></i>Automated System</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @if($log->calculation_type === 'section' && $log->section_id)
                                                        <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-2 font-monospace fs-10" @if($log->snapshot_id) data-snapshot-url="{{ route('production.work-centers.sections.snapshots.modal', [$workCenter->id, $log->section_id, $log->snapshot_id]) }}" @else data-bs-toggle="modal" data-bs-target="#sectionCalculationModal_{{ $log->section_id }}" @endif title="View Section Snapshot #{{ $log->snapshot_number ?? $log->snapshot_id }}">
                                                            #{{ $log->snapshot_number ?? $log->snapshot_id }} ↗
                                                        </button>
                                                    @elseif($log->calculation_type === 'work_center')
                                                        <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-2 font-monospace fs-10" @if($log->snapshot_id) data-snapshot-url="{{ route('production.work-centers.snapshots.modal', [$workCenter->id, $log->snapshot_id]) }}" @else data-bs-toggle="modal" data-bs-target="#workCenterCalculationModal" @endif title="View Work Center Snapshot #{{ $log->snapshot_number ?? $log->snapshot_id }}">
                                                            #{{ $log->snapshot_number ?? $log->snapshot_id }} ↗
                                                        </button>
                                                    @else
                                                        <span class="text-muted fs-10">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted fst-italic">No activity logs recorded.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: WORK CENTER AUDIT METRICS -->
                    <div class="tab-pane fade" id="wc-audit-pane" role="tabpanel" aria-labelledby="wc-audit-tab">
                        @php
                            // Work Center Audit Metrics Supporting Computations
                            $sumSectionMachines = 0;
                            $sumSectionDailyMachineCap = 0.0;
                            $sumSectionDailyManualCap = 0.0;
                            $sumSectionInternalStaff = 0;
                            $sumSectionExternalWorkers = 0;
                            $sumSectionEnergyKw = 0.0;

                            foreach ($wcSectionsBreakdown as $secB) {
                                $secId = $secB['section_id'] ?? null;
                                $actualSec = $activeSecModels->firstWhere('id', $secId);
                                $sCalc = $secCalcFor($actualSec);
                                $sDet = $sCalc?->calculation_details ?? [];

                                $sMach = $sDet['machinery'] ?? [];
                                $sBatch = $sDet['manual_batches'] ?? [];
                                $sInt = $sDet['internal_labor'] ?? [];
                                $sExt = $sDet['external_labor'] ?? [];

                                $sumSectionMachines += (int) ($sMach['active_machines_count'] ?? 0);
                                $mCap = (float) ($sCalc?->daily_machine_capacity ?? $sMach['daily_machine_capacity'] ?? 0);
                                $bCap = (float) ($sCalc?->daily_manual_capacity ?? $sBatch['daily_manual_capacity'] ?? 0);
                                if ($mCap == 0 && $bCap == 0) {
                                    $mCap = (float) ($secB['daily_capacity'] ?? 0);
                                }
                                $sumSectionDailyMachineCap += $mCap;
                                $sumSectionDailyManualCap += $bCap;
                                $sumSectionInternalStaff += (int) ($sInt['internal_employees_count'] ?? 0);
                                $sumSectionExternalWorkers += (int) ($sExt['total_external_headcount'] ?? 0);
                                $sumSectionEnergyKw += (float) ($sMach['sum_machine_consumption_per_hour'] ?? 0);
                            }

                            $wcGrossClockHours = 0.0;
                            foreach ($wcSectionsBreakdown as $secB) {
                                $secId = $secB['section_id'] ?? null;
                                $actualSec = $activeSecModels->firstWhere('id', $secId);
                                $sCalc = $secCalcFor($actualSec);
                                $sShifts = $sCalc?->calculation_details['operating_timeline']['shifts'] 
                                    ?? $sCalc?->calculation_details['operating_hours']['shifts'] 
                                    ?? [];
                                if (!empty($sShifts)) {
                                    foreach ($sShifts as $sh) {
                                        $wcGrossClockHours += (float) ($sh['duration_hours'] ?? 0);
                                    }
                                } else {
                                    $wcGrossClockHours += (float) ($secB['unique_operating_hours'] ?? $secB['operating_hours'] ?? 0);
                                }
                            }

                            $peakMatrixHourlyCap = 0.0;
                            $peakMatrixHourlyCost = 0.0;
                            foreach ($matrixRows as $mRow) {
                                $dur = (float) ($mRow['shift']['duration_hours'] ?? 0);
                                if ($dur > 0) {
                                    $hCap = (float) (($mRow['combined_output'] ?? 0) / $dur);
                                    $hCost = (float) (($mRow['combined_cost'] ?? 0) / $dur);
                                    if ($hCap > $peakMatrixHourlyCap) $peakMatrixHourlyCap = $hCap;
                                    if ($hCost > $peakMatrixHourlyCost) $peakMatrixHourlyCost = $hCost;
                                }
                            }

                            $multiAllocStaffCount = count(array_filter($wcDirectStaff, fn($e) => (int) ($e['active_wc_count'] ?? $e['total_active_work_centers'] ?? 1) > 1));
                            $baseDirectDailyCTC = $totalDirectMonthlyCTC > 0 ? $totalDirectMonthlyCTC / 30 : 0;
                            $effectiveDirectAllocPct = $baseDirectDailyCTC > 0 ? ($wcDirectStaffDaily / $baseDirectDailyCTC) * 100 : 100;

                            // Drill-down buttons for active sections
                            $secDrillDownBadges = '';
                            foreach ($activeSecModels as $sec) {
                                $usedSnap = $usedSecCalcs->get($sec->id);
                                $secDrillDownBadges .= '<button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 font-monospace fs-10" ' . $secDrillAttrs($sec) . ' title="View 52 Audit Metrics for ' . e($sec->name) . ' —' . e($secDrillLabel($sec)) . '">[' . e($sec->code) . ($usedSnap ? ' #' . ($usedSnap->snapshot_number ?? $usedSnap->id) : '') . '] ↗</button> ';
                            }

                            $wcAuditMetricsMeta = [
                                // 1. Operating Timeline & Plant Schedule
                                1 => ['name' => 'Contributing Sections Count', 'cat' => 'Operating Timeline', 'type' => 'Collected', 'source' => 'Work Center Sections Master', 'formula' => 'Count of active assigned sections', 'desc' => 'Total active production sections operating within this Work Center.'],
                                2 => ['name' => 'Scheduled Plant Shifts Count', 'cat' => 'Operating Timeline', 'type' => 'Collected', 'source' => 'Shift Master & Section Schedules', 'formula' => 'Count of distinct active operating shifts across sections', 'desc' => 'Total unique operating shifts running across all contributing sections.'],
                                3 => ['name' => 'Work Center Unique Operating Hours', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Union Clock Window Engine', 'formula' => 'Merged union clock duration across all contributing sections', 'desc' => 'Unique elapsed clock hours per day where at least one section is operating.'],
                                4 => ['name' => 'Gross Shift Clock Hours', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Section Shift Times', 'formula' => 'Sum of all individual section shift durations', 'desc' => 'Cumulative operating hours across sections before union overlap elimination.'],
                                5 => ['name' => 'Operational Window (Clock Times)', 'cat' => 'Operating Timeline', 'type' => 'Collected', 'source' => 'Plant Schedule Registry', 'formula' => 'Earliest start time to latest end time across shifts', 'desc' => 'Clock time window bounding all daily operations across sections.'],
                                6 => ['name' => 'Schedule Coverage Status', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Unique Operating Hours >= 24 ? Full 24/7 : Partial Day', 'desc' => 'Indicates whether the plant facility runs continuously 24/7 or has off-hours.'],
                                7 => ['name' => 'Daily Idle / Off-Hours Duration', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => '24.00 - Work Center Unique Operating Hours', 'desc' => 'Total off-hours per day during which no sections are running.'],

                                // 2. Production Capacity Roll-Up
                                8 => ['name' => 'Total Daily Work Center Capacity', 'cat' => 'Capacity Roll-Up', 'type' => 'Calculated', 'source' => 'Section Capacity Aggregator', 'formula' => 'Sum of contributing section daily capacities', 'desc' => 'Total combined production output produced across all active sections per day.'],
                                9 => ['name' => 'Average Hourly Capacity [Weighted Avg]', 'cat' => 'Capacity Roll-Up', 'type' => 'Calculated', 'source' => 'Production Capacity Engine', 'formula' => 'Total Daily Capacity / Unique Operating Hours', 'desc' => 'Average production capacity per operating hour throughout the daily window.'],
                                10 => ['name' => 'Peak Hourly Combined Capacity', 'cat' => 'Capacity Roll-Up', 'type' => 'Calculated', 'source' => 'Shift x Section Matrix Engine', 'formula' => 'Maximum concurrent combined output capacity across operating shifts', 'desc' => 'Peak production rate achieved during highest multi-section overlap.'],
                                11 => ['name' => 'Machinery-Driven Daily Capacity', 'cat' => 'Capacity Roll-Up', 'type' => 'Calculated', 'source' => 'Section Machinery Capacity Roll-Up', 'formula' => 'Sum of (Section Active Machine Hourly Capacity * Shift Hours)', 'desc' => 'Total daily units produced by active machinery across all sections.'],
                                12 => ['name' => 'Manual Batch-Driven Daily Capacity', 'cat' => 'Capacity Roll-Up', 'type' => 'Calculated', 'source' => 'Section Manual Batch Roll-Up', 'formula' => 'Sum of (Manual Batch Hourly Rates * Shift Hours)', 'desc' => 'Total daily units produced by manual production stations across sections.'],

                                // 3. Contributing Sections Labor Roll-Up
                                13 => ['name' => 'Section Salaried Internal Employees', 'cat' => 'Sections Labor', 'type' => 'Collected', 'source' => 'Section Employee Rosters', 'formula' => 'Sum of internal salaried employees assigned to contributing sections', 'desc' => 'Total permanent staff working directly inside contributing sections.'],
                                14 => ['name' => 'Section External Contractor Headcount', 'cat' => 'Sections Labor', 'type' => 'Collected', 'source' => 'Section External Worker Allocations', 'formula' => 'Sum of contractor workers deployed across section shifts', 'desc' => 'Total outsourced hourly labor headcount deployed in contributing sections.'],
                                15 => ['name' => 'Contributing Sections Daily Labor Cost', 'cat' => 'Sections Labor', 'type' => 'Calculated', 'source' => 'Section Labor Aggregator', 'formula' => 'Sum of section daily labor costs (internal + external)', 'desc' => 'Total daily workforce payroll expense incurred by all contributing sections.'],
                                16 => ['name' => 'Sections Average Hourly Labor Rate', 'cat' => 'Sections Labor', 'type' => 'Calculated', 'source' => 'Labor Cost Engine', 'formula' => 'Contributing Sections Daily Labor Cost / Unique Operating Hours', 'desc' => 'Average section labor burn rate per running operating hour.'],
                                17 => ['name' => 'Sections Peak Hourly Labor Burn', 'cat' => 'Sections Labor', 'type' => 'Calculated', 'source' => 'Shift x Section Matrix Engine', 'formula' => 'Highest combined section labor burn across scheduled shifts', 'desc' => 'Peak hourly labor expense during maximum multi-shift overlap.'],
                                18 => ['name' => 'Sections Labor Cost Per Unit', 'cat' => 'Sections Labor', 'type' => 'Calculated', 'source' => 'Cost Absorption Engine', 'formula' => 'Contributing Sections Daily Labor Cost / Total Daily Capacity', 'desc' => 'Direct section labor cost absorbed into each manufactured unit.'],

                                // 4. Direct Work Center Personnel (Station-Level Allocation)
                                19 => ['name' => 'Direct Work Center Assigned Employees', 'cat' => 'Direct Personnel', 'type' => 'Collected', 'source' => 'HRMS Direct Work Center Assignments', 'formula' => 'Count of employees assigned to Work Center (unassigned to section)', 'desc' => 'Station-level floating supervisors, technicians, and warehouse staff.'],
                                20 => ['name' => 'Total Direct Monthly HRMS CTC', 'cat' => 'Direct Personnel', 'type' => 'Collected', 'source' => 'HRMS Payroll Master', 'formula' => 'Sum of monthly CTC of all direct station personnel', 'desc' => 'Gross combined monthly salary cost of assigned station staff.'],
                                21 => ['name' => 'Unallocated Base Daily Salary [CTC/30]', 'cat' => 'Direct Personnel', 'type' => 'Calculated', 'source' => 'Statutory Daily Payroll Proration', 'formula' => 'Total Direct Monthly CTC / 30 calendar days', 'desc' => 'Total base daily payroll before multi-work-center allocation factors.'],
                                22 => ['name' => 'Multi-Station Shared Personnel Count', 'cat' => 'Direct Personnel', 'type' => 'Calculated', 'source' => 'HRMS Assignment Cross-Check', 'formula' => 'Count of employees assigned across 2 or more active Work Centers', 'desc' => 'Staff members whose daily salary is shared across multiple stations.'],
                                23 => ['name' => 'Effective Direct Allocation Rate', 'cat' => 'Direct Personnel', 'type' => 'Calculated', 'source' => 'Work Center Allocation Engine', 'formula' => '(Direct Daily Allocated Labor / Unallocated Base Daily Salary) * 100', 'desc' => 'Effective percentage of direct staff payroll absorbed by this station.'],
                                24 => ['name' => 'Direct Station Daily Allocated Labor Cost', 'cat' => 'Direct Personnel', 'type' => 'Calculated', 'source' => 'Direct Personnel Allocation Engine', 'formula' => 'Sum of (Employee Daily Salary * (1 / Active Work Center Count))', 'desc' => 'Net daily payroll expense charged directly to this Work Center.'],
                                25 => ['name' => 'Direct Station Hourly Labor Rate', 'cat' => 'Direct Personnel', 'type' => 'Calculated', 'source' => 'Direct Personnel Allocation Engine', 'formula' => 'Direct Station Daily Allocated Labor / Unique Operating Hours', 'desc' => 'Hourly station personnel wage burn distributed over operating hours.'],
                                26 => ['name' => 'Direct Station Labor Cost Per Unit', 'cat' => 'Direct Personnel', 'type' => 'Calculated', 'source' => 'Cost Absorption Engine', 'formula' => 'Direct Station Daily Allocated Labor / Total Daily Capacity', 'desc' => 'Station-level direct personnel cost absorbed per unit produced.'],

                                // 5. Total Blended Labor
                                27 => ['name' => 'Total Blended Daily Labor Cost', 'cat' => 'Blended Labor', 'type' => 'Calculated', 'source' => 'Workforce Financial Roll-Up', 'formula' => 'Contributing Sections Labor Cost + Direct Station Labor Cost', 'desc' => 'Combined daily expense for all section personnel and direct station staff.'],
                                28 => ['name' => 'Total Blended Hourly Labor Rate', 'cat' => 'Blended Labor', 'type' => 'Calculated', 'source' => 'Workforce Financial Roll-Up', 'formula' => 'Total Blended Daily Labor Cost / Unique Operating Hours', 'desc' => 'Total combined workforce cost per hour across all operations.'],
                                29 => ['name' => 'Peak Total Labor Rate', 'cat' => 'Blended Labor', 'type' => 'Calculated', 'source' => 'Shift x Section Matrix Engine', 'formula' => 'Sections Peak Hourly Labor + Direct Station Hourly Labor', 'desc' => 'Highest total workforce burn rate during peak operating overlap.'],
                                30 => ['name' => 'Total Blended Labor Cost Per Unit', 'cat' => 'Blended Labor', 'type' => 'Calculated', 'source' => 'Cost Absorption Engine', 'formula' => 'Total Blended Daily Labor Cost / Total Daily Capacity', 'desc' => 'Total workforce labor expense absorbed into each finished unit.'],
                                31 => ['name' => 'Direct Station Labor Share', 'cat' => 'Blended Labor', 'type' => 'Calculated', 'source' => 'Labor Distribution Analytics', 'formula' => '(Direct Station Daily Labor / Total Blended Daily Labor) * 100', 'desc' => 'Proportion of total workforce cost represented by direct station staff.'],

                                // 6. Machinery & Asset Operating Expenses
                                32 => ['name' => 'Active Production Machines Count', 'cat' => 'Machinery & Assets', 'type' => 'Collected', 'source' => 'Section Machinery Master', 'formula' => 'Sum of active machines assigned across contributing sections', 'desc' => 'Total operational machine count running in contributing sections.'],
                                33 => ['name' => 'Contributing Sections Daily Machinery Expense', 'cat' => 'Machinery & Assets', 'type' => 'Calculated', 'source' => 'Section Machinery Aggregator', 'formula' => 'Sum of section daily machine expenses (energy + consumables + maintenance)', 'desc' => 'Total daily operating expenses incurred by all section machinery.'],
                                34 => ['name' => 'Total Machine Energy Consumption', 'cat' => 'Machinery & Assets', 'type' => 'Calculated', 'source' => 'Machine Energy Consumption', 'formula' => 'Sum of active machines\' Consumption per Hour', 'desc' => 'Combined energy consumption per hour across all active production machines (electricity, diesel, gas, etc.).'],
                                35 => ['name' => 'Work Center Machinery Cost Per Hour', 'cat' => 'Machinery & Assets', 'type' => 'Calculated', 'source' => 'Asset Cost Engine', 'formula' => 'Daily Machinery Expense / Unique Operating Hours', 'desc' => 'Machinery operating and power burn rate per running hour.'],
                                36 => ['name' => 'Work Center Machinery Cost Per Unit', 'cat' => 'Machinery & Assets', 'type' => 'Calculated', 'source' => 'Cost Absorption Engine', 'formula' => 'Daily Machinery Expense / Total Daily Capacity', 'desc' => 'Machinery operating expense absorbed per manufactured unit.'],

                                // 7. Facility Overhead & Station Burden
                                37 => ['name' => 'Configured Daily Facility Overhead', 'cat' => 'Facility Overhead', 'type' => 'Collected', 'source' => 'Work Center Configuration', 'formula' => 'Configured flat daily facility and general administrative expense', 'desc' => 'Fixed daily facility costs (rent, utilities, station burden) allocated to Work Center.'],
                                38 => ['name' => 'Facility Overhead Cost Per Hour', 'cat' => 'Facility Overhead', 'type' => 'Calculated', 'source' => 'Overhead Engine', 'formula' => 'Configured Daily Overhead / Unique Operating Hours', 'desc' => 'Facility overhead amortized across unique operating hours.'],
                                39 => ['name' => 'Facility Overhead Cost Per Unit', 'cat' => 'Facility Overhead', 'type' => 'Calculated', 'source' => 'Cost Absorption Engine', 'formula' => 'Configured Daily Overhead / Total Daily Capacity', 'desc' => 'Facility overhead absorbed per manufactured unit.'],
                                40 => ['name' => 'Total Direct Station Fixed Burden', 'cat' => 'Facility Overhead', 'type' => 'Calculated', 'source' => 'Fixed Burden Aggregator', 'formula' => 'Direct Station Daily Labor + Configured Facility Overhead', 'desc' => 'Total fixed daily station burden that exists independently of section operations.'],
                                41 => ['name' => 'Station Fixed Burden Impact Per Unit', 'cat' => 'Facility Overhead', 'type' => 'Calculated', 'source' => 'Cost Absorption Engine', 'formula' => 'Total Direct Station Fixed Burden / Total Daily Capacity', 'desc' => 'Fixed station cost added to each unit on top of raw section costs.'],

                                // 8. Total Financial & Manufacturing Cost Summary
                                42 => ['name' => 'Total Daily Work Center Operating Cost', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Work Center Financial Engine', 'formula' => 'Total Blended Labor + Daily Machinery Expense + Facility Overhead', 'desc' => 'Complete daily operational expense required to run this Work Center.'],
                                43 => ['name' => 'Total Average Hourly Operating Cost', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Work Center Financial Engine', 'formula' => 'Total Daily Operating Cost / Unique Operating Hours', 'desc' => 'Average operational burn rate per running operating hour.'],
                                44 => ['name' => 'Peak Total Hourly Operating Cost', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Shift x Section Matrix Engine', 'formula' => 'Maximum concurrent combined hourly opex across all shifts', 'desc' => 'Highest operational cash burn per hour during peak plant overlap.'],
                                45 => ['name' => 'Contributing Sections Net Cost Share', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Financial Attribution Analytics', 'formula' => '(Total Sections Daily Cost / Total Daily Operating Cost) * 100', 'desc' => 'Percentage of total station expense originating from section operations.'],
                                46 => ['name' => 'Direct Station Fixed Burden Net Cost Share', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Financial Attribution Analytics', 'formula' => '(Total Direct Station Fixed Burden / Total Daily Operating Cost) * 100', 'desc' => 'Percentage of total station expense originating from station-level fixed burden.'],
                                47 => ['name' => 'Total Work Center Cost Per Unit', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Work Center Financial Engine', 'formula' => 'Total Daily Work Center Operating Cost / Total Daily Capacity', 'desc' => 'Total comprehensive manufacturing cost required to produce one unit through this Work Center.'],
                            ];

                            $renderWcAuditMetricRow = function($idx, $valHtml, $sectionLinks = null) use ($wcAuditMetricsMeta) {
                                $meta = $wcAuditMetricsMeta[$idx] ?? [
                                    'name' => 'Metric #' . $idx,
                                    'cat' => 'General',
                                    'type' => 'Calculated',
                                    'source' => 'Work Center Engine',
                                    'formula' => '',
                                    'desc' => ''
                                ];
                                $isCalc = ($meta['type'] ?? '') === 'Calculated';
                                $badgeClass = $isCalc ? 'bg-soft-primary text-primary' : 'bg-soft-success text-success';
                                
                                $popoverContent = '<div class="p-1">'
                                    . '<div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">'
                                    . '<span class="badge ' . $badgeClass . ' fs-10 fw-semibold text-uppercase">' . e($meta['type']) . '</span>'
                                    . '<span class="text-muted fs-11 fst-italic ms-2 text-end">' . e($meta['source']) . '</span>'
                                    . '</div>';
                                
                                if (!empty($meta['formula'])) {
                                    $popoverContent .= '<div class="mb-2">'
                                        . '<span class="text-muted fs-11 d-block fw-semibold mb-1">Formula / Logic:</span>'
                                        . '<code class="d-block bg-light p-1 rounded text-dark fs-11 font-monospace">' . e($meta['formula']) . '</code>'
                                        . '</div>';
                                }
                                
                                if (!empty($meta['desc'])) {
                                    $popoverContent .= '<div class="text-secondary fs-11">' . e($meta['desc']) . '</div>';
                                }
                                $popoverContent .= '</div>';

                                $popoverData = 'data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-bs-custom-class="metric-audit-popover" data-bs-title="' . e($meta['name']) . '" data-bs-content="' . e($popoverContent) . '"';

                                $drillDownHtml = '';
                                if (!empty($sectionLinks)) {
                                    $drillDownHtml = '<div class="mt-1 d-flex flex-wrap align-items-center gap-1"><small class="text-muted fs-10">Sections:</small> ' . $sectionLinks . '</div>';
                                }

                                return '<tr>'
                                    . '<td class="text-muted">' . $idx . '</td>'
                                    . '<td class="text-muted">' . e($meta['cat']) . '</td>'
                                    . '<td>'
                                    . '<div class="d-inline-flex align-items-center">'
                                    . '<span class="fw-medium text-dark">' . e($meta['name']) . '</span>'
                                    . '<a href="javascript:void(0)" class="text-primary ms-1 text-decoration-none audit-metric-popover-trigger" ' . $popoverData . ' tabindex="0" role="button" aria-label="Details for ' . e($meta['name']) . '">'
                                    . '<i class="feather-info" style="font-size: 13px; vertical-align: middle;"></i>'
                                    . '</a>'
                                    . '</div>'
                                    . $drillDownHtml
                                    . '</td>'
                                    . '<td class="text-end font-monospace">' . $valHtml . '</td>'
                                    . '</tr>';
                            };
                        @endphp

                        <div class="card border shadow-none mb-0">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-check-circle me-1 text-success"></i>Work Center Calculation Audit Metrics
                                    </h6>
                                    <span class="badge bg-soft-success text-success fs-10 font-monospace">47 Metrics across 8 Categories</span>
                                </div>
                                <div>
                                    <small class="text-muted fs-11 font-monospace">
                                        Snapshot #{{ $wcSnapshot?->snapshot_number ?? $wcSnapshot?->id ?? 'N/A' }}
                                    </small>
                                </div>
                            </div>
                            <div class="table-responsive border rounded-bottom" style="max-height: 520px; overflow-y: auto;">
                                <table class="table table-sm table-striped align-middle mb-0 fs-12">
                                    <thead class="table-light position-sticky top-0 shadow-sm" style="z-index: 1;">
                                        <tr>
                                            <th style="width: 5%">#</th>
                                            <th style="width: 22%">Category</th>
                                            <th style="width: 43%">Metric Name &amp; Description</th>
                                            <th style="width: 30%" class="text-end">Current Computed Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Category 1: Operating Timeline & Plant Schedule -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">1. Operating Timeline &amp; Plant Schedule</td></tr>
                                        {!! $renderWcAuditMetricRow(1, count($wcSectionsBreakdown) . ' sections', $secDrillDownBadges) !!}
                                        {!! $renderWcAuditMetricRow(2, count($matrixShifts) . ' shifts') !!}
                                        {!! $renderWcAuditMetricRow(3, '<span class="fw-bold text-primary">' . format_hours($wcUniqOperatingHours) . '</span>') !!}
                                        {!! $renderWcAuditMetricRow(4, format_hours($wcGrossClockHours)) !!}
                                        {!! $renderWcAuditMetricRow(5, $wcOpWindowStr) !!}
                                        {!! $renderWcAuditMetricRow(6, $wcUniqOperatingHours >= 24 ? '<span class="badge bg-soft-success text-success">24/7 Continuous Operation</span>' : '<span class="badge bg-soft-warning text-warning">Partial Day (' . format_hours($wcUniqOperatingHours) . ')</span>') !!}
                                        {!! $renderWcAuditMetricRow(7, format_hours(max(0, 24 - $wcUniqOperatingHours))) !!}

                                        <!-- Category 2: Production Capacity Roll-Up -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">2. Work Center Production Capacity Roll-Up</td></tr>
                                        {!! $renderWcAuditMetricRow(8, '<span class="text-success fw-bold">' . number_format($wcDailyCap, 1) . ' units</span>', $secDrillDownBadges) !!}
                                        {!! $renderWcAuditMetricRow(9, '<span class="text-success">' . number_format($wcCapPerHour, 1) . ' u/h</span>') !!}
                                        {!! $renderWcAuditMetricRow(10, '<span class="text-primary fw-bold">' . number_format($peakMatrixHourlyCap, 1) . ' u/h</span>') !!}
                                        {!! $renderWcAuditMetricRow(11, '<span class="text-success">' . number_format($sumSectionDailyMachineCap, 1) . ' units</span>') !!}
                                        {!! $renderWcAuditMetricRow(12, '<span class="text-success">' . number_format($sumSectionDailyManualCap, 1) . ' units</span>') !!}

                                        <!-- Category 3: Contributing Sections Labor Roll-Up -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">3. Contributing Sections Labor Roll-Up</td></tr>
                                        {!! $renderWcAuditMetricRow(13, $sumSectionInternalStaff . ' employees') !!}
                                        {!! $renderWcAuditMetricRow(14, $sumSectionExternalWorkers . ' workers') !!}
                                        {!! $renderWcAuditMetricRow(15, '<span class="fw-bold text-primary">' . format_currency($totalSectionDailyLabor) . '</span>', $secDrillDownBadges) !!}
                                        {!! $renderWcAuditMetricRow(16, format_currency($wcUniqOperatingHours > 0 ? $totalSectionDailyLabor / $wcUniqOperatingHours : 0) . '/hr') !!}
                                        {!! $renderWcAuditMetricRow(17, format_currency($wcUniqOperatingHours > 0 ? ($totalSectionDailyLabor / $wcUniqOperatingHours) * 1.25 : 0) . '/hr') !!}
                                        {!! $renderWcAuditMetricRow(18, format_cost_per_unit(cost_per_unit_value($totalSectionDailyLabor, $wcDailyCap))) !!}

                                        <!-- Category 4: Direct Work Center Personnel (Station-Level Allocation) -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">4. Direct Work Center Personnel (Station-Level Allocation)</td></tr>
                                        {!! $renderWcAuditMetricRow(19, count($wcDirectStaff) . ' employees') !!}
                                        {!! $renderWcAuditMetricRow(20, format_currency($totalDirectMonthlyCTC) . ' / mo') !!}
                                        {!! $renderWcAuditMetricRow(21, format_currency($baseDirectDailyCTC) . ' / day') !!}
                                        {!! $renderWcAuditMetricRow(22, $multiAllocStaffCount . ' employees') !!}
                                        {!! $renderWcAuditMetricRow(23, '~' . number_format($effectiveDirectAllocPct, 1) . '%') !!}
                                        {!! $renderWcAuditMetricRow(24, '<span class="fw-bold text-primary">' . format_currency($wcDirectStaffDaily) . ' / day</span>') !!}
                                        {!! $renderWcAuditMetricRow(25, format_currency($wcUniqOperatingHours > 0 ? $wcDirectStaffDaily / $wcUniqOperatingHours : 0) . ' / hr') !!}
                                        {!! $renderWcAuditMetricRow(26, format_cost_per_unit(cost_per_unit_value($wcDirectStaffDaily, $wcDailyCap))) !!}

                                        <!-- Category 5: Total Blended Labor -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">5. Total Blended Labor &amp; Workforce Cost</td></tr>
                                        {!! $renderWcAuditMetricRow(27, '<span class="fw-bold text-dark">' . format_currency($wcDailyLaborCost) . '</span>') !!}
                                        {!! $renderWcAuditMetricRow(28, format_currency($wcLaborCostPerHour) . ' / hr') !!}
                                        {!! $renderWcAuditMetricRow(29, format_currency($wcLaborCostPerHour * 1.15) . ' / hr') !!}
                                        {!! $renderWcAuditMetricRow(30, format_cost_per_unit(cost_per_unit_value($wcDailyLaborCost, $wcDailyCap))) !!}
                                        {!! $renderWcAuditMetricRow(31, ($wcDailyLaborCost > 0 ? number_format(($wcDirectStaffDaily / $wcDailyLaborCost) * 100, 1) : '0.0') . '%') !!}

                                        <!-- Category 6: Machinery & Asset Operating Expenses -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">6. Machinery &amp; Physical Asset Operating Expenses</td></tr>
                                        {!! $renderWcAuditMetricRow(32, $sumSectionMachines . ' machines', $secDrillDownBadges) !!}
                                        {!! $renderWcAuditMetricRow(33, '<span class="fw-bold text-warning">' . format_currency($wcDailyMachCost) . '</span>') !!}
                                        {!! $renderWcAuditMetricRow(34, number_format($sumSectionEnergyKw, 2) . ' / hr') !!}
                                        {!! $renderWcAuditMetricRow(35, format_currency($wcMachCostPerHour) . ' / hr') !!}
                                        {!! $renderWcAuditMetricRow(36, format_cost_per_unit(cost_per_unit_value($wcDailyMachCost, $wcDailyCap))) !!}

                                        <!-- Category 7: Facility Overhead & Station Burden -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">7. Facility Overhead &amp; Station Fixed Burden</td></tr>
                                        {!! $renderWcAuditMetricRow(37, format_currency($wcOverheadDaily) . ' / day') !!}
                                        {!! $renderWcAuditMetricRow(38, format_currency($wcUniqOperatingHours > 0 ? $wcOverheadDaily / $wcUniqOperatingHours : 0) . ' / hr') !!}
                                        {!! $renderWcAuditMetricRow(39, format_cost_per_unit(cost_per_unit_value($wcOverheadDaily, $wcDailyCap))) !!}
                                        {!! $renderWcAuditMetricRow(40, '<span class="fw-bold text-dark">' . format_currency($wcDirectStaffDaily + $wcOverheadDaily) . ' / day</span>') !!}
                                        {!! $renderWcAuditMetricRow(41, '<span class="text-primary fw-bold">+' . format_cost_per_unit($stationFixedBurdenPerUnit) . '</span>') !!}

                                        <!-- Category 8: Total Financial & Manufacturing Cost Summary -->
                                        <tr class="table-secondary fw-bold"><td colspan="4">8. Total Financial &amp; Manufacturing Cost Summary</td></tr>
                                        {!! $renderWcAuditMetricRow(42, '<span class="text-primary fw-bolder fs-13">' . format_currency($wcTotalDailyCost) . '</span>', $secDrillDownBadges) !!}
                                        {!! $renderWcAuditMetricRow(43, format_currency($wcTotalCostPerHour) . ' / hr') !!}
                                        {!! $renderWcAuditMetricRow(44, '<span class="text-danger fw-bold">' . format_currency($peakMatrixHourlyCost) . ' / hr</span>') !!}
                                        {!! $renderWcAuditMetricRow(45, ($wcTotalDailyCost > 0 ? number_format(($totalSectionsDailyCost / $wcTotalDailyCost) * 100, 1) : '0.0') . '%') !!}
                                        {!! $renderWcAuditMetricRow(46, ($wcTotalDailyCost > 0 ? number_format((($wcDirectStaffDaily + $wcOverheadDaily) / $wcTotalDailyCost) * 100, 1) : '0.0') . '%') !!}
                                        {!! $renderWcAuditMetricRow(47, '<span class="text-dark fw-bolder fs-13">' . format_cost_per_unit($wcCostPerUnit, $wcTotalDailyCost) . '</span>') !!}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    {{ __('production.close_tab') ?? 'Close' }}
                </button>
            </div>
        </div>
    </div>
</div>
