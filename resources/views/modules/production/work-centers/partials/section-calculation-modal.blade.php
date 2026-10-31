<!-- Calculation Breakdown Modal for Section {{ $section->id }} -->
<div class="modal fade" id="sectionCalculationModal_{{ $section->id }}" tabindex="-1" aria-labelledby="sectionCalculationModalLabel_{{ $section->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="feather-bar-chart-2"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-15 mb-0 d-flex align-items-center flex-wrap gap-1" id="sectionCalculationModalLabel_{{ $section->id }}">
                            {{ __('production.calculation_breakdown') }} &mdash; <span class="font-monospace text-primary">{{ $section->code }}</span> ({{ $section->name }})
                            @if(!$section->isActive())
                                <span class="badge bg-soft-secondary text-muted font-monospace fs-11 ms-1">Inactive</span>
                            @elseif($section->latestCalculation)
                                <span class="badge bg-soft-success text-success font-monospace fs-11 ms-1">Snapshot #{{ $section->latestCalculation->snapshot_number ?? $section->latestCalculation->id }}</span>
                            @endif
                        </h5>
                        <small class="text-muted fs-11">{{ __('production.work_center') }}: {{ $workCenter->name }} [{{ $workCenter->code }}]</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                @if(!$section->isActive())
                    <div class="alert alert-soft-secondary border-secondary-subtle d-flex align-items-center gap-2 mb-3 py-2 px-3 fs-12">
                        <i class="feather-pause-circle text-secondary fs-14 flex-shrink-0"></i>
                        <span><strong>Section Inactive:</strong> This section is marked inactive. Its values are not calculated and it does not contribute to the Work Center totals.</span>
                    </div>
                @endif
                @php
                    $isSecActive = $section->isActive();
                    $secCalc = $isSecActive ? $section->latestCalculation : null;
                    // TEMPORARY AUDIT OVERRIDE: Blocked auto-calculation on view render.
                    // If $secCalc is null, values gracefully default to zeros.
                    $details = $secCalc->calculation_details ?? [];
                    $opTimeline = $details['operating_timeline'] ?? [];
                    $machData = $details['machinery'] ?? [];
                    $batchData = $details['manual_batches'] ?? [];
                    $intLabor = $details['internal_labor'] ?? [];
                    $extLabor = $details['external_labor'] ?? [];
                    $combLabor = $details['combined_labor'] ?? [];
                    $finSummary = $details['financial_summary'] ?? [];
                    $timelineIntervals = $details['timeline_intervals'] ?? [];

                    // Financial & Capacity KPIs
                    $dailyCap = (float) ($finSummary['total_daily_capacity'] ?? $secCalc->daily_capacity ?? 0);
                    $avgHourlyCap = (float) ($finSummary['total_hourly_capacity'] ?? $secCalc->capacity_per_hour ?? 0);
                    $peakHourlyCap = (float) ($finSummary['peak_total_hourly_capacity'] ?? $avgHourlyCap);

                    $dailyCost = (float) ($finSummary['total_daily_operating_cost'] ?? $secCalc->total_daily_operating_cost ?? 0);
                    $avgHourlyCost = (float) ($finSummary['total_hourly_operating_cost'] ?? $secCalc->total_operating_cost_per_hour ?? 0);
                    $peakHourlyCost = (float) ($finSummary['peak_total_hourly_operating_cost'] ?? $avgHourlyCost);
                    $costPerUnit = (float) ($finSummary['cost_per_unit'] ?? 0);

                    // Operating Hours
                    $uniqHours = (float) ($opTimeline['section_unique_operating_hours'] ?? $secCalc->unique_operating_hours ?? 0);
                    $hasOverlap = (bool) ($opTimeline['has_overlapping_shifts'] ?? false);
                    $overlapHours = (float) ($opTimeline['shift_overlap_hours'] ?? 0);
                    $advisoryNotes = $opTimeline['advisory_notes'] ?? null;
                    $hasGaps = (bool) ($opTimeline['has_inactive_gaps'] ?? false);
                    $gapHours = (float) ($opTimeline['inactive_gap_hours'] ?? 0);

                    // Subtotals
                    $dailyMachCap = (float) ($machData['daily_machine_capacity'] ?? 0);
                    $dailyMachCost = (float) ($machData['total_daily_machine_expense'] ?? $secCalc->total_daily_machine_cost ?? 0);
                    $hourlyMachCost = (float) ($machData['hourly_machine_expense'] ?? $secCalc->machine_cost_per_hour ?? 0);

                    $dailyManualCap = (float) ($batchData['daily_manual_capacity'] ?? 0);
                    $hourlyManualCap = (float) ($batchData['hourly_manual_capacity'] ?? 0);

                    $dailyLaborCost = (float) ($combLabor['total_daily_labor_cost'] ?? $secCalc->total_daily_labor_cost ?? 0);
                    $avgLaborRate = (float) ($combLabor['total_average_hourly_labor_rate'] ?? $secCalc->labor_cost_per_hour ?? 0);
                    $peakLaborRate = (float) ($combLabor['peak_hourly_labor_rate'] ?? $avgLaborRate);

                    $dailyInternalCost = (float) ($intLabor['total_daily_internal_labor_cost'] ?? 0);
                    $avgInternalRate = (float) ($intLabor['average_internal_hourly_rate'] ?? 0);
                    $dailyExternalCost = (float) ($extLabor['total_daily_external_labor_cost'] ?? 0);
                    $avgExternalRate = (float) ($extLabor['average_external_hourly_rate'] ?? 0);
                @endphp

                @if(!$secCalc)
                    <div class="alert alert-soft-warning py-3 px-4 fs-13 mb-4 d-flex align-items-center gap-3 rounded-3 border border-warning-subtle">
                        <i class="feather-alert-triangle fs-22 text-warning flex-shrink-0"></i>
                        <div>
                            <strong class="d-block text-dark mb-0.5">Calculation Snapshot Pending</strong>
                            <span class="text-muted fs-12">An initial calculation snapshot has not been recorded yet. Click the <strong>Recalculate</strong> button on the Work Center page to generate live capacity and cost metrics.</span>
                        </div>
                    </div>
                @endif

                @if(!empty($advisoryNotes))
                    <div class="alert alert-warning py-2.5 px-3 fs-12 mb-3 d-flex align-items-center gap-2 rounded-3 border-warning">
                        <i class="feather-alert-triangle fs-16 text-warning"></i>
                        <div>
                            <strong class="d-block text-dark">{{ $advisoryNotes }}</strong>
                            <span class="text-muted fs-11">Assign at least one active shift to configure operating hours and production capacities for this section.</span>
                        </div>
                    </div>
                @endif

                <!-- Top 6 KPI Summary Cards -->
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
                                {{ number_format($dailyCap, 1) }}
                                <span class="fs-11 fw-normal text-muted">units/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Avg: <strong class="text-dark">{{ number_format($avgHourlyCap, 1) }} u/h</strong></span>
                                <span>Peak: <strong class="text-primary">{{ number_format($peakHourlyCap, 1) }} u/h</strong></span>
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
                                {{ format_currency($dailyCost) }}
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Avg: <strong class="text-dark">{{ format_currency($avgHourlyCost) }}/hr</strong></span>
                                <span>Peak: <strong class="text-danger">{{ format_currency($peakHourlyCost) }}/hr</strong></span>
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
                                {{ format_currency($costPerUnit) }}
                                <span class="fs-11 fw-normal text-muted">/ unit</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Mach: <strong class="text-dark">{{ format_currency($machData['machine_cost_per_unit'] ?? 0) }}</strong></span>
                                <span>Labor: <strong class="text-dark">{{ format_currency($combLabor['total_labor_cost_per_unit'] ?? 0) }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Operating Window -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-clock me-1 text-primary"></i>Operating Hours
                                </span>
                                @if($hasOverlap)
                                    <span class="badge bg-soft-warning text-warning font-monospace fs-10">Overlap Active</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary font-monospace fs-10">Standard</span>
                                @endif
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {{ format_hours($uniqHours, false) }}
                                <span class="fs-11 fw-normal text-muted">hrs/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1">
                                Window: <span class="fw-semibold text-dark">{{ $opTimeline['operational_window_start'] ?? 'None' }} &ndash; {{ $opTimeline['operational_window_end'] ?? 'None' }}</span>
                                @if($hasOverlap)
                                    &bull; <span class="text-warning fw-semibold">{{ format_hours($overlapHours) }} overlap</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- 5. Machinery Expense -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-cpu me-1 text-secondary"></i>Machinery Opex
                                </span>
                                <span class="badge bg-soft-secondary text-secondary font-monospace fs-10">{{ count($machData['machines'] ?? []) }} Active</span>
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {{ format_currency($dailyMachCost) }}
                                <span class="fs-11 fw-normal text-muted">/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Rate: <strong class="text-dark">{{ format_currency($hourlyMachCost) }}/hr</strong></span>
                                <span>Daily Cap: <strong class="text-dark">{{ number_format($dailyMachCap, 1) }} u</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Labor Expense -->
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">
                                    <i class="feather-users me-1 text-info"></i>Total Labor Cost
                                </span>
                                <span class="badge bg-soft-info text-info font-monospace fs-10">Internal + External</span>
                            </div>
                            <div class="fs-22 fw-bolder text-dark font-monospace">
                                {{ format_currency($dailyLaborCost) }}
                                <span class="fs-11 fw-normal text-muted">/day</span>
                            </div>
                            <div class="text-muted fs-11 mt-1 d-flex justify-content-between">
                                <span>Avg: <strong class="text-dark">{{ format_currency($avgLaborRate) }}/hr</strong></span>
                                <span>Peak: <strong class="text-primary">{{ format_currency($peakLaborRate) }}/hr</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pill Tabs Navigation -->
                <ul class="nav nav-pills bg-light p-1.5 rounded-3 mb-3 gap-1" id="secCalcAuditTabs_{{ $section->id }}" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold fs-12 py-1.5 px-3 rounded-2" 
                                id="audit-machines-tab-{{ $section->id }}" 
                                data-bs-toggle="pill" 
                                data-bs-target="#audit-machines-pane-{{ $section->id }}" 
                                type="button" 
                                role="tab">
                            <i class="feather-cpu me-1"></i>Machines & Energy ({{ count($machData['machines'] ?? []) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-12 py-1.5 px-3 rounded-2" 
                                id="audit-manual-tab-{{ $section->id }}" 
                                data-bs-toggle="pill" 
                                data-bs-target="#audit-manual-pane-{{ $section->id }}" 
                                type="button" 
                                role="tab">
                            <i class="feather-tool me-1"></i>Manual Batches ({{ count($batchData['batches'] ?? []) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-12 py-1.5 px-3 rounded-2" 
                                id="audit-labor-tab-{{ $section->id }}" 
                                data-bs-toggle="pill" 
                                data-bs-target="#audit-labor-pane-{{ $section->id }}" 
                                type="button" 
                                role="tab">
                            <i class="feather-users me-1"></i>Personnel Labor
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-12 py-1.5 px-3 rounded-2" 
                                id="audit-timeline-tab-{{ $section->id }}" 
                                data-bs-toggle="pill" 
                                data-bs-target="#audit-timeline-pane-{{ $section->id }}" 
                                type="button" 
                                role="tab">
                            <i class="feather-calendar me-1"></i>Timeline Intervals ({{ count($timelineIntervals) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-12 py-1.5 px-3 rounded-2" 
                                id="audit-all-tab-{{ $section->id }}" 
                                data-bs-toggle="pill" 
                                data-bs-target="#audit-all-pane-{{ $section->id }}" 
                                type="button" 
                                role="tab">
                            <i class="feather-file-text me-1"></i>52 Audit Metrics
                        </button>
                    </li>
                </ul>

                <!-- Tab Panes -->
                <div class="tab-content" id="secCalcAuditTabContent_{{ $section->id }}">
                    <!-- Pane 1: Machines & Energy -->
                    <div class="tab-pane fade show active" id="audit-machines-pane-{{ $section->id }}" role="tabpanel">
                        <div class="p-2.5 rounded-3 bg-light border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2 fs-12">
                            <div>
                                <span class="text-muted">Total Power:</span>
                                <strong class="text-dark font-monospace ms-1">{{ number_format($machData['sum_machine_consumption_per_hour'] ?? 0, 2) }} kW</strong>
                            </div>
                            <div>
                                <span class="text-muted">Base Energy Cost:</span>
                                <strong class="text-dark font-monospace ms-1">{{ format_currency($machData['calculated_machine_base_cost_per_hour'] ?? 0) }}/hr</strong>
                            </div>
                            @if(($machData['total_machine_additional_cost_per_hour'] ?? 0) > 0)
                                <div>
                                    <span class="text-muted">Additional Wear/Tooling:</span>
                                    <strong class="text-warning font-monospace ms-1">+{{ format_currency($machData['total_machine_additional_cost_per_hour'] ?? 0) }}/hr</strong>
                                </div>
                            @endif
                            @if(($machData['total_machine_deductibles_per_hour'] ?? 0) > 0)
                                <div>
                                    <span class="text-muted">Deductibles/Subsidies:</span>
                                    <strong class="text-success font-monospace ms-1">-{{ format_currency($machData['total_machine_deductibles_per_hour'] ?? 0) }}/hr</strong>
                                </div>
                            @endif
                            <div>
                                <span class="text-muted">Net Hourly Machine Opex:</span>
                                <strong class="text-primary font-monospace ms-1">{{ format_currency($hourlyMachCost) }}/hr</strong>
                            </div>
                        </div>

                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 14%">Machine</th>
                                        <th style="width: 10%" class="text-end">Hourly Capacity</th>
                                        <th style="width: 12%" class="text-end">Power (kW)</th>
                                        <th style="width: 12%" class="text-end">Energy Rate</th>
                                        <th style="width: 12%" class="text-end">Base Energy</th>
                                        <th style="width: 12%" class="text-end">Entered Cost</th>
                                        <th style="width: 14%">
                                            Reconciliation
                                            <span class="text-primary ms-1 cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="Defaults to baseline energy cost (Consumption × Energy Rate). If adjusted higher, the surplus is accounted as an additional operational expense (e.g., tooling wear, lubricants, routine servicing). If adjusted lower, the difference is accounted as an applied operating grant, energy subsidy, or tariff rebate.">
                                                <i class="feather-info"></i>
                                            </span>
                                        </th>
                                        <th style="width: 14%" class="text-end">Daily Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($machData['machines'] ?? [] as $m)
                                        <tr>
                                            <td>
                                                <span class="badge bg-soft-secondary text-secondary font-monospace fs-10">[{{ $m['code'] ?? 'N/A' }}]</span>
                                                <strong class="text-dark d-block fs-12">{{ $m['name'] ?? 'Machine' }}</strong>
                                            </td>
                                            <td class="text-end font-monospace text-success fw-semibold">
                                                {{ number_format((float) ($m['cap_hr'] ?? $m['capacity'] ?? 0), 1) }} u/h
                                            </td>
                                            <td class="text-end font-monospace text-muted">
                                                {{ number_format((float) ($m['consumption_per_hour'] ?? 0), 2) }}
                                            </td>
                                            <td class="text-end font-monospace text-muted">
                                                {{ format_currency((float) ($m['energy_rate_per_unit'] ?? 0)) }}
                                            </td>
                                            <td class="text-end font-monospace text-dark">
                                                {{ format_currency((float) ($m['calculated_base_cost_hr'] ?? $m['base_energy_cost_per_hour'] ?? 0)) }}/hr
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-primary">
                                                {{ format_currency((float) ($m['operating_cost_per_hour'] ?? 0)) }}/hr
                                            </td>
                                            <td>
                                                @php
                                                    $mAddCost = (float) ($m['additional_cost_per_hour'] ?? $m['additional_cost'] ?? 0);
                                                    $mDedCost = (float) ($m['deductibles_per_hour'] ?? $m['deductibles'] ?? 0);
                                                @endphp
                                                @if($mAddCost > 0)
                                                    <span class="badge bg-soft-warning text-warning fs-10" data-bs-toggle="tooltip" data-bs-placement="top" title="Additional operational expense (tooling wear, lubricants, routine maintenance): {{ $m['cost_explanation'] ?? '' }}">
                                                        +{{ format_currency($mAddCost) }}/hr Expense
                                                    </span>
                                                @elseif($mDedCost > 0)
                                                    <span class="badge bg-soft-success text-success fs-10" data-bs-toggle="tooltip" data-bs-placement="top" title="Applied operating grant or energy subsidy: {{ $m['cost_explanation'] ?? '' }}">
                                                        -{{ format_currency($mDedCost) }}/hr Subsidy
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary fs-10" data-bs-toggle="tooltip" data-bs-placement="top" title="Operating cost matches baseline energy calculation (Consumption × Energy Rate).">
                                                        Exact Base
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-dark">
                                                {{ format_currency((float) ($m['daily_cost'] ?? 0)) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-3 text-muted fst-italic">
                                                No active machines configured in this section.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($machData['machines']))
                                    <tfoot class="table-light border-top fw-bold">
                                        <tr>
                                            <td>Total Active Machines:</td>
                                            <td class="text-end font-monospace text-success">{{ number_format($machData['hourly_machine_capacity'] ?? 0, 1) }} u/h</td>
                                            <td class="text-end font-monospace">{{ number_format($machData['sum_machine_consumption_per_hour'] ?? 0, 2) }} kW</td>
                                            <td colspan="2" class="text-end font-monospace text-muted">{{ format_currency($machData['calculated_machine_base_cost_per_hour'] ?? 0) }}/hr</td>
                                            <td class="text-end font-monospace text-primary">{{ format_currency($hourlyMachCost) }}/hr</td>
                                            <td></td>
                                            <td class="text-end font-monospace text-dark">{{ format_currency($dailyMachCost) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                    <!-- Pane 2: Manual Batches -->
                    <div class="tab-pane fade" id="audit-manual-pane-{{ $section->id }}" role="tabpanel">
                        <div class="p-2.5 rounded-3 bg-light border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2 fs-12">
                            <div>
                                <span class="text-muted">Total Manual Batches:</span>
                                <strong class="text-dark font-monospace ms-1">{{ count($batchData['batches'] ?? []) }}</strong>
                            </div>
                            <div>
                                <span class="text-muted">Weighted Avg Capacity:</span>
                                <strong class="text-success font-monospace ms-1">{{ number_format($hourlyManualCap, 1) }} units/hr</strong>
                                <i class="feather-info text-muted ms-1 cursor-pointer" title="It is average production per hour throughout section shifts. Formula: {{ number_format($dailyManualCap, 1) }} units / {{ format_hours($uniqHours) }} = {{ number_format($hourlyManualCap, 1) }} u/h" data-bs-toggle="tooltip"></i>
                            </div>
                            <div>
                                <span class="text-muted">Peak Concurrent Capacity:</span>
                                <strong class="text-primary font-monospace ms-1">{{ number_format($batchData['peak_hourly_manual_capacity'] ?? 0, 1) }} units/hr</strong>
                            </div>
                            <div>
                                <span class="text-muted">Total Daily Manual Output:</span>
                                <strong class="text-dark font-monospace ms-1">{{ number_format($dailyManualCap, 1) }} units/day</strong>
                            </div>
                        </div>

                        <style>
                            .batch-expand-btn {
                                width: 24px;
                                height: 24px;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                border-radius: 4px;
                                padding: 0;
                                cursor: pointer;
                                transition: all 0.2s ease-in-out;
                            }
                            .batch-expand-btn[aria-expanded="false"] {
                                background-color: var(--bs-primary, #3e60d5);
                                color: #ffffff;
                                border: 1px solid var(--bs-primary, #3e60d5);
                            }
                            .batch-expand-btn[aria-expanded="true"] {
                                background-color: #f1f5f9;
                                color: var(--bs-primary, #3e60d5);
                                border: 1px solid #cbd5e1;
                            }
                            .batch-expand-btn .batch-chevron {
                                transition: transform 0.2s ease-in-out;
                            }
                            .batch-expand-btn[aria-expanded="true"] .batch-chevron {
                                transform: rotate(90deg);
                            }
                            .batch-detail-row > td {
                                padding: 0 !important;
                                border: 0 !important;
                            }
                        </style>
                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 32%">Batch Name</th>
                                        <th style="width: 22%">Assigned Shift</th>
                                        <th style="width: 15%" class="text-end">Hourly Capacity</th>
                                        <th style="width: 15%" class="text-end">Shift Eff. Hours</th>
                                        <th style="width: 16%" class="text-end">
                                            Daily Output
                                            <i class="feather-info text-muted ms-1 cursor-pointer" title="Daily Output = Batch Capacity/hr × Shift Effective Hours" data-bs-toggle="tooltip"></i>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($batchData['batches'] ?? [] as $b)
                                        @php
                                            $teamMembers = $b['team_members'] ?? $b['assigned_employees'] ?? [];
                                            $contractors = $b['contractor_allocations'] ?? [];
                                            $intCount = (int) ($b['total_internal_workers'] ?? count($teamMembers));

                                            $extCount = (int) ($b['total_external_workers'] ?? 0);
                                            if (!isset($b['total_external_workers'])) {
                                                foreach ($contractors as $cStr) {
                                                    if (preg_match('/^(\d+)x/', $cStr, $m)) {
                                                        $extCount += (int) $m[1];
                                                    } else {
                                                        $extCount += 1;
                                                    }
                                                }
                                            }

                                            $empDetails = $b['internal_employees_details'] ?? [];
                                            $allocDetails = $b['external_contractors_details'] ?? [];

                                            $intHourlyCost = (float) ($b['internal_hourly_cost'] ?? 0);
                                            $intDailyCost  = (float) ($b['internal_daily_cost'] ?? 0);
                                            $extHourlyCost = (float) ($b['external_hourly_cost'] ?? 0);
                                            $extDailyCost  = (float) ($b['external_daily_cost'] ?? 0);
                                            $totBatchHourly = (float) ($b['total_batch_hourly_cost'] ?? ($intHourlyCost + $extHourlyCost));
                                            $totBatchDaily  = (float) ($b['total_batch_daily_cost'] ?? ($intDailyCost + $extDailyCost));
                                            $costPerUnit    = (float) ($b['cost_per_unit'] ?? 0);

                                            // Graceful live fallback for legacy snapshot if cost details were not precalculated
                                            if (empty($empDetails) && empty($allocDetails) && isset($b['id'])) {
                                                $liveBatch = \App\Domains\Production\Models\SectionManualProduction::with(['employees.employee.designation', 'externalWorkerAllocations.payGrade'])->find($b['id']);
                                                if ($liveBatch) {
                                                    $shiftClock = (float) ($b['shift_clock_hours'] ?? ($shiftMap[$b['shift_id'] ?? null]['duration_hours'] ?? 8.0));
                                                    if ($shiftClock <= 0) $shiftClock = 8.0;

                                                    if (empty($empDetails) && $liveBatch->employees->isNotEmpty()) {
                                                        foreach ($liveBatch->employees as $be) {
                                                            $emp = $be->employee;
                                                            if ($emp) {
                                                                $mSal = (float) ($emp->current_salary ?? 0);
                                                                $dSal = round($mSal / 30, 4);
                                                                $hRate = round($dSal / $shiftClock, 4);
                                                                $empDetails[] = [
                                                                    'name' => $emp->full_name,
                                                                    'employee_code' => $emp->employee_id ?? null,
                                                                    'hourly_cost' => $hRate,
                                                                    'daily_cost' => $dSal,
                                                                ];
                                                                $intHourlyCost += $hRate;
                                                                $intDailyCost += $dSal;
                                                            }
                                                        }
                                                    }

                                                    if (empty($allocDetails) && $liveBatch->externalWorkerAllocations->isNotEmpty()) {
                                                        foreach ($liveBatch->externalWorkerAllocations as $alloc) {
                                                            $hc = (int) ($alloc->number_of_external_workers ?? 1);
                                                            $pg = $alloc->payGrade;
                                                            $rph = (float) ($pg?->hourly_rate ?? 0);
                                                            $hBurn = round($hc * $rph, 4);
                                                            $dCost = round($hBurn * $shiftClock, 4);
                                                            $allocDetails[] = [
                                                                'workers_count' => $hc,
                                                                'pay_grade_name' => $pg?->name ?? 'Contractor',
                                                                'rate_per_hour' => $rph,
                                                                'hourly_cost' => $hBurn,
                                                                'daily_cost' => $dCost,
                                                            ];
                                                            $extHourlyCost += $hBurn;
                                                            $extDailyCost += $dCost;
                                                        }
                                                    }
                                                    $totBatchHourly = round($intHourlyCost + $extHourlyCost, 4);
                                                    $totBatchDaily = round($intDailyCost + $extDailyCost, 4);
                                                    $dailyCapVal = (float) ($b['daily_cap'] ?? 0);
                                                    $costPerUnit = $dailyCapVal > 0 ? round($totBatchDaily / $dailyCapVal, 4) : 0;
                                                }
                                            }
                                        @endphp
                                        <tr class="align-middle">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button" 
                                                            class="btn btn-xs batch-expand-btn rounded p-0 flex-shrink-0" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#batchCollapse_{{ $section->id }}_{{ $b['id'] ?? $loop->index }}" 
                                                            aria-expanded="false" 
                                                            aria-controls="batchCollapse_{{ $section->id }}_{{ $b['id'] ?? $loop->index }}"
                                                            title="Expand/collapse workforce & cost breakdown">
                                                        <i class="feather-chevron-right fs-12 batch-chevron"></i>
                                                    </button>
                                                    <div>
                                                        <strong class="text-dark d-block fs-13 cursor-pointer batch-expand-trigger" 
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#batchCollapse_{{ $section->id }}_{{ $b['id'] ?? $loop->index }}">
                                                            {{ $b['batch_name'] ?? $b['name'] ?? 'Manual Batch' }}
                                                        </strong>
                                                        <div class="d-flex align-items-center gap-1.5 mt-0.5">
                                                            <span class="badge bg-soft-info text-info font-monospace fs-10 px-1.5 py-0.5">
                                                                <i class="feather-user me-0.5"></i>{{ $intCount }} Emp
                                                            </span>
                                                            <span class="badge bg-soft-primary text-primary font-monospace fs-10 px-1.5 py-0.5">
                                                                <i class="feather-users me-0.5"></i>{{ $extCount }} Ext
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-info text-info font-monospace">{{ $b['shift_name'] ?? 'N/A' }}</span>
                                            </td>
                                            <td class="text-end font-monospace text-success fw-semibold">
                                                {{ number_format((float) ($b['cap_hr'] ?? $b['capacity_per_hour'] ?? 0), 1) }} u/h
                                            </td>
                                            <td class="text-end font-monospace text-muted">
                                                {{ format_hours((float) ($b['eff_hrs'] ?? $b['shift_effective_hours'] ?? 0)) }}
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-dark">
                                                {{ number_format((float) ($b['daily_cap'] ?? $b['daily_output'] ?? 0), 1) }} units
                                                @if(!empty($b['formula']))
                                                    <i class="feather-info text-muted ms-1 cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $b['formula'] }}"></i>
                                                @endif
                                            </td>
                                        </tr>
                                        <!-- Collapsible Sub-Row for Workforce & Cost Details -->
                                        <tr class="batch-detail-row p-0 border-0" style="border: none;">
                                            <td colspan="5" class="p-0 border-0">
                                                <div class="collapse batch-collapse-pane" id="batchCollapse_{{ $section->id }}_{{ $b['id'] ?? $loop->index }}">
                                                    <div class="p-3 bg-light border-top border-bottom">
                                                        @php
                                                            $hasInt = !empty($empDetails);
                                                            $hasExt = !empty($allocDetails) || !empty($contractors);
                                                        @endphp
                                                        <div class="row g-2.5">
                                                            @if($hasInt && $hasExt)
                                                                {{-- CASE 1: BOTH HAVE DATA -> Two side-by-side cards --}}
                                                                <!-- Col 1: Internal Employees -->
                                                                <div class="col-md-6">
                                                                    <div class="card border shadow-none mb-0 bg-white">
                                                                        <div class="card-header bg-soft-info py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                                            <span class="fw-bold fs-12 text-dark">
                                                                                <i class="feather-user text-info me-1"></i>Internal Employees ({{ $intCount }})
                                                                            </span>
                                                                            <span class="badge bg-white text-info border border-info font-monospace fs-10">
                                                                                {{ format_currency($intHourlyCost) }}/hr &bull; {{ format_currency($intDailyCost) }}/day
                                                                            </span>
                                                                        </div>
                                                                        <div class="card-body p-0">
                                                                            <div class="table-responsive">
                                                                                <table class="table table-sm table-hover align-middle mb-0 fs-11">
                                                                                    <thead class="table-light">
                                                                                        <tr>
                                                                                            <th class="ps-3 py-1.5">Employee</th>
                                                                                            <th class="text-end py-1.5">Hourly Cost</th>
                                                                                            <th class="text-end pe-3 py-1.5">Daily Salary</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        @foreach($empDetails as $emp)
                                                                                            <tr>
                                                                                                <td class="ps-3 py-1.5">
                                                                                                    <strong class="text-dark d-block">{{ $emp['name'] }}</strong>
                                                                                                    @if(!empty($emp['employee_code']))
                                                                                                        <span class="text-muted font-monospace fs-10">[{{ $emp['employee_code'] }}]</span>
                                                                                                    @endif
                                                                                                </td>
                                                                                                <td class="text-end font-monospace text-muted py-1.5">
                                                                                                    {{ format_currency($emp['hourly_cost']) }}/hr
                                                                                                </td>
                                                                                                <td class="text-end font-monospace text-dark fw-semibold pe-3 py-1.5">
                                                                                                    {{ format_currency($emp['daily_cost']) }}/day
                                                                                                </td>
                                                                                            </tr>
                                                                                        @endforeach
                                                                                    </tbody>
                                                                                    <tfoot class="table-light border-top">
                                                                                        <tr>
                                                                                            <td class="ps-3 py-1.5 fw-bold text-dark">Subtotal ({{ $intCount }} staff):</td>
                                                                                            <td class="text-end font-monospace text-info fw-bold py-1.5">{{ format_currency($intHourlyCost) }}/hr</td>
                                                                                            <td class="text-end font-monospace text-info fw-bold pe-3 py-1.5">{{ format_currency($intDailyCost) }}/day</td>
                                                                                        </tr>
                                                                                    </tfoot>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Col 2: External Workers -->
                                                                <div class="col-md-6">
                                                                    <div class="card border shadow-none mb-0 bg-white">
                                                                        <div class="card-header bg-soft-primary py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                                            <span class="fw-bold fs-12 text-dark">
                                                                                <i class="feather-users text-primary me-1"></i>External Workers ({{ $extCount }})
                                                                            </span>
                                                                            <span class="badge bg-white text-primary border border-primary font-monospace fs-10">
                                                                                {{ format_currency($extHourlyCost) }}/hr &bull; {{ format_currency($extDailyCost) }}/day
                                                                            </span>
                                                                        </div>
                                                                        <div class="card-body p-0">
                                                                            @if(!empty($allocDetails))
                                                                                <div class="table-responsive">
                                                                                    <table class="table table-sm table-hover align-middle mb-0 fs-11">
                                                                                        <thead class="table-light">
                                                                                            <tr>
                                                                                                <th class="ps-3 py-1.5">Allocation / Pay Grade</th>
                                                                                                <th class="text-end py-1.5">Hourly Burn</th>
                                                                                                <th class="text-end pe-3 py-1.5">Daily Cost</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            @foreach($allocDetails as $alloc)
                                                                                                <tr>
                                                                                                    <td class="ps-3 py-1.5">
                                                                                                        <strong class="text-dark d-block">{{ $alloc['workers_count'] }}x {{ $alloc['pay_grade_name'] }}</strong>
                                                                                                        <span class="text-muted font-monospace fs-10">Rate: {{ format_currency($alloc['rate_per_hour']) }}/hr</span>
                                                                                                    </td>
                                                                                                    <td class="text-end font-monospace text-muted py-1.5">
                                                                                                        {{ format_currency($alloc['hourly_cost']) }}/hr
                                                                                                    </td>
                                                                                                    <td class="text-end font-monospace text-dark fw-semibold pe-3 py-1.5">
                                                                                                        {{ format_currency($alloc['daily_cost']) }}/day
                                                                                                    </td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                        <tfoot class="table-light border-top">
                                                                                            <tr>
                                                                                                <td class="ps-3 py-1.5 fw-bold text-dark">Subtotal ({{ $extCount }} workers):</td>
                                                                                                <td class="text-end font-monospace text-primary fw-bold py-1.5">{{ format_currency($extHourlyCost) }}/hr</td>
                                                                                                <td class="text-end font-monospace text-primary fw-bold pe-3 py-1.5">{{ format_currency($extDailyCost) }}/day</td>
                                                                                            </tr>
                                                                                        </tfoot>
                                                                                    </table>
                                                                                </div>
                                                                            @elseif(!empty($contractors))
                                                                                <div class="p-2">
                                                                                    @foreach($contractors as $cStr)
                                                                                        <div class="text-dark fs-11 py-1 px-2 border-bottom"><i class="feather-user-check text-primary me-1"></i>{{ $cStr }}</div>
                                                                                    @endforeach
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                            @elseif(!$hasInt && $hasExt)
                                                                {{-- CASE 2: 0 INTERNAL, ACTIVE EXTERNAL -> Slim strip for 0 Internal + Full Width Card for External --}}
                                                                <div class="col-12">
                                                                    <div class="py-2 px-3 rounded-2 bg-white border d-flex align-items-center justify-content-between fs-11">
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span class="badge bg-soft-info text-info font-monospace px-1.5 py-0.5">
                                                                                <i class="feather-user me-0.5"></i>0 Emp
                                                                            </span>
                                                                            <span class="text-muted"><strong class="text-dark">Internal Employees:</strong> No salaried staff assigned to this batch</span>
                                                                        </div>
                                                                        <span class="text-muted font-monospace fs-11">{{ format_currency(0) }}/hr &bull; {{ format_currency(0) }}/day</span>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12">
                                                                    <div class="card border shadow-none mb-0 bg-white">
                                                                        <div class="card-header bg-soft-primary py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                                            <span class="fw-bold fs-12 text-dark">
                                                                                <i class="feather-users text-primary me-1"></i>External Workers ({{ $extCount }})
                                                                            </span>
                                                                            <span class="badge bg-white text-primary border border-primary font-monospace fs-10">
                                                                                {{ format_currency($extHourlyCost) }}/hr &bull; {{ format_currency($extDailyCost) }}/day
                                                                            </span>
                                                                        </div>
                                                                        <div class="card-body p-0">
                                                                            @if(!empty($allocDetails))
                                                                                <div class="table-responsive">
                                                                                    <table class="table table-sm table-hover align-middle mb-0 fs-11">
                                                                                        <thead class="table-light">
                                                                                            <tr>
                                                                                                <th class="ps-3 py-1.5" style="width: 45%;">Allocation / Pay Grade</th>
                                                                                                <th class="text-end py-1.5" style="width: 18%;">Hourly Rate</th>
                                                                                                <th class="text-end py-1.5" style="width: 17%;">Hourly Burn</th>
                                                                                                <th class="text-end pe-3 py-1.5" style="width: 20%;">Daily Cost</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            @foreach($allocDetails as $alloc)
                                                                                                <tr>
                                                                                                    <td class="ps-3 py-1.5">
                                                                                                        <strong class="text-dark d-block">{{ $alloc['workers_count'] }}x {{ $alloc['pay_grade_name'] }}</strong>
                                                                                                    </td>
                                                                                                    <td class="text-end font-monospace text-muted py-1.5">
                                                                                                        {{ format_currency($alloc['rate_per_hour']) }}/hr
                                                                                                    </td>
                                                                                                    <td class="text-end font-monospace text-muted py-1.5">
                                                                                                        {{ format_currency($alloc['hourly_cost']) }}/hr
                                                                                                    </td>
                                                                                                    <td class="text-end font-monospace text-dark fw-semibold pe-3 py-1.5">
                                                                                                        {{ format_currency($alloc['daily_cost']) }}/day
                                                                                                    </td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                        <tfoot class="table-light border-top">
                                                                                            <tr>
                                                                                                <td class="ps-3 py-1.5 fw-bold text-dark">Subtotal ({{ $extCount }} workers):</td>
                                                                                                <td></td>
                                                                                                <td class="text-end font-monospace text-primary fw-bold py-1.5">{{ format_currency($extHourlyCost) }}/hr</td>
                                                                                                <td class="text-end font-monospace text-primary fw-bold pe-3 py-1.5">{{ format_currency($extDailyCost) }}/day</td>
                                                                                            </tr>
                                                                                        </tfoot>
                                                                                    </table>
                                                                                </div>
                                                                            @elseif(!empty($contractors))
                                                                                <div class="p-2">
                                                                                    @foreach($contractors as $cStr)
                                                                                        <div class="text-dark fs-11 py-1 px-2 border-bottom"><i class="feather-user-check text-primary me-1"></i>{{ $cStr }}</div>
                                                                                    @endforeach
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                            @elseif($hasInt && !$hasExt)
                                                                {{-- CASE 3: ACTIVE INTERNAL, 0 EXTERNAL -> Full Width Card for Internal + Slim strip for 0 External --}}
                                                                <div class="col-12">
                                                                    <div class="card border shadow-none mb-0 bg-white">
                                                                        <div class="card-header bg-soft-info py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                                            <span class="fw-bold fs-12 text-dark">
                                                                                <i class="feather-user text-info me-1"></i>Internal Employees ({{ $intCount }})
                                                                            </span>
                                                                            <span class="badge bg-white text-info border border-info font-monospace fs-10">
                                                                                {{ format_currency($intHourlyCost) }}/hr &bull; {{ format_currency($intDailyCost) }}/day
                                                                            </span>
                                                                        </div>
                                                                        <div class="card-body p-0">
                                                                            <div class="table-responsive">
                                                                                <table class="table table-sm table-hover align-middle mb-0 fs-11">
                                                                                    <thead class="table-light">
                                                                                        <tr>
                                                                                            <th class="ps-3 py-1.5" style="width: 50%;">Employee</th>
                                                                                            <th class="text-end py-1.5" style="width: 25%;">Hourly Cost</th>
                                                                                            <th class="text-end pe-3 py-1.5" style="width: 25%;">Daily Salary</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        @foreach($empDetails as $emp)
                                                                                            <tr>
                                                                                                <td class="ps-3 py-1.5">
                                                                                                    <strong class="text-dark">{{ $emp['name'] }}</strong>
                                                                                                    @if(!empty($emp['employee_code']))
                                                                                                        <span class="text-muted font-monospace fs-10">[{{ $emp['employee_code'] }}]</span>
                                                                                                    @endif
                                                                                                </td>
                                                                                                <td class="text-end font-monospace text-muted py-1.5">
                                                                                                    {{ format_currency($emp['hourly_cost']) }}/hr
                                                                                                </td>
                                                                                                <td class="text-end font-monospace text-dark fw-semibold pe-3 py-1.5">
                                                                                                    {{ format_currency($emp['daily_cost']) }}/day
                                                                                                </td>
                                                                                            </tr>
                                                                                        @endforeach
                                                                                    </tbody>
                                                                                    <tfoot class="table-light border-top">
                                                                                        <tr>
                                                                                            <td class="ps-3 py-1.5 fw-bold text-dark">Subtotal ({{ $intCount }} staff):</td>
                                                                                            <td class="text-end font-monospace text-info fw-bold py-1.5">{{ format_currency($intHourlyCost) }}/hr</td>
                                                                                            <td class="text-end font-monospace text-info fw-bold pe-3 py-1.5">{{ format_currency($intDailyCost) }}/day</td>
                                                                                        </tr>
                                                                                    </tfoot>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12">
                                                                    <div class="py-2 px-3 rounded-2 bg-white border d-flex align-items-center justify-content-between fs-11">
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span class="badge bg-soft-primary text-primary font-monospace px-1.5 py-0.5">
                                                                                <i class="feather-users me-0.5"></i>0 Ext
                                                                            </span>
                                                                            <span class="text-muted"><strong class="text-dark">External Workers:</strong> No external contractors allocated to this batch</span>
                                                                        </div>
                                                                        <span class="text-muted font-monospace fs-11">{{ format_currency(0) }}/hr &bull; {{ format_currency(0) }}/day</span>
                                                                    </div>
                                                                </div>

                                                            @else
                                                                {{-- CASE 4: NEITHER HAS DATA --}}
                                                                <div class="col-12">
                                                                    <div class="py-3 px-3 rounded-2 bg-white border text-center text-muted fst-italic fs-11">
                                                                        <i class="feather-alert-circle me-1 text-warning"></i>No internal employees or external contractors have been allocated to this manual batch yet.
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            <!-- Full-Width Summary Banner -->
                                                            <div class="col-12 mt-1">
                                                                <div class="p-2.5 rounded-3 bg-white border d-flex flex-wrap align-items-center justify-content-between gap-3">
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                                                                            <i class="feather-dollar-sign"></i>
                                                                        </div>
                                                                        <div>
                                                                            <span class="fs-12 fw-bold text-dark d-block">Total Batch Labor Cost</span>
                                                                            <span class="text-muted fs-11">
                                                                                Shift: {{ $b['shift_name'] ?? 'N/A' }} ({{ format_hours($b['eff_hrs'] ?? 8) }} eff. hrs) &bull; Daily Cap: {{ number_format((float) ($b['daily_cap'] ?? 0), 1) }} units
                                                                                @if(!empty($b['formula']))
                                                                                    &bull; <span class="font-monospace text-muted fs-10" title="Daily Output Formula"><i class="feather-help-circle me-0.5"></i>{{ $b['formula'] }}</span>
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="d-flex align-items-center gap-4 flex-wrap">
                                                                        <div>
                                                                            <span class="text-muted fs-10 text-uppercase d-block">Hourly Burn</span>
                                                                            <strong class="font-monospace fs-12 text-dark">{{ format_currency($totBatchHourly) }}/hr</strong>
                                                                        </div>
                                                                        <div>
                                                                            <span class="text-muted fs-10 text-uppercase d-block">Daily Labor Expense</span>
                                                                            <strong class="font-monospace fs-12 text-dark">{{ format_currency($totBatchDaily) }}/day</strong>
                                                                        </div>
                                                                        @if($costPerUnit > 0)
                                                                            <div class="border-start ps-3">
                                                                                <span class="text-muted fs-10 text-uppercase d-block">Cost Per Unit Output</span>
                                                                                <strong class="font-monospace fs-12 text-success">{{ format_currency($costPerUnit) }} / unit</strong>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted fst-italic">
                                                No active manual production batches configured in this section.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($batchData['batches']))
                                    <tfoot class="table-light border-top fw-bold">
                                        <tr>
                                            <td colspan="2">Total Manual Production:</td>
                                            <td class="text-end font-monospace text-success">
                                                Avg {{ number_format($hourlyManualCap, 1) }} u/h
                                                <i class="feather-info text-muted ms-1 cursor-pointer" title="It is average production per hour throughout section shifts. Formula: {{ number_format($dailyManualCap, 1) }} units / {{ format_hours($uniqHours) }} = {{ number_format($hourlyManualCap, 1) }} u/h" data-bs-toggle="tooltip"></i>
                                            </td>
                                            <td></td>
                                            <td class="text-end font-monospace text-dark">{{ number_format($dailyManualCap, 1) }} units/day</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                    <!-- Pane 3: Personnel Labor -->
                    <div class="tab-pane fade" id="audit-labor-pane-{{ $section->id }}" role="tabpanel">
                        <!-- Internal Employees -->
                        <div class="card border rounded-3 mb-4 shadow-none">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                <span class="fw-bold fs-13 text-dark">
                                    <i class="feather-user-check text-info me-1"></i>Internal Employees (Salaried Staff)
                                </span>
                                <span class="text-muted fs-11">
                                    Daily Salary = Monthly CTC / 30 days &bull; Hourly Rate = Daily Salary / Total Shift Duration (Clock Hours)
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 22%">Employee</th>
                                                <th style="width: 16%" class="text-end">Monthly CTC</th>
                                                <th style="width: 16%" class="text-end">Daily Salary (CTC/30)</th>
                                                <th style="width: 16%">Shift (Clock Hours)</th>
                                                <th style="width: 15%" class="text-end">Hourly Salary Rate</th>
                                                <th style="width: 15%" class="text-end">
                                                    Daily Cost
                                                    <i class="feather-info text-muted ms-1 cursor-pointer" title="Daily Cost = Daily Salary (Monthly CTC / 30)" data-bs-toggle="tooltip"></i>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($intLabor['employees'] ?? [] as $emp)
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info font-monospace fs-10">[{{ $emp['employee_code'] ?? 'N/A' }}]</span>
                                                        <strong class="text-dark d-block">{{ $emp['employee_name'] ?? $emp['name'] ?? 'N/A' }}</strong>
                                                    </td>
                                                    <td class="text-end font-monospace text-muted">
                                                        {{ format_currency((float) ($emp['monthly_salary'] ?? $emp['hrms_net_monthly_salary'] ?? 0)) }}
                                                    </td>
                                                    <td class="text-end font-monospace text-dark fw-semibold">
                                                        {{ format_currency((float) ($emp['daily_salary'] ?? 0)) }}
                                                    </td>
                                                    <td>
                                                        @if(!empty($emp['is_outside_section_shifts']))
                                                            <span class="badge bg-soft-warning text-warning font-monospace" data-bs-toggle="tooltip" data-bs-placement="top" title="Employee is assigned to this shift in HRMS, which is external to this section's scheduled shift window">
                                                                {{ $emp['shift_name'] ?? 'N/A' }} (Ext)
                                                            </span>
                                                        @else
                                                            <span class="badge bg-soft-success text-success font-monospace">{{ $emp['shift_name'] ?? 'N/A' }}</span>
                                                        @endif
                                                        <small class="text-muted d-block fs-11">{{ format_hours((float) ($emp['shift_duration_hours'] ?? $emp['shift_clock_hours'] ?? 0)) }}</small>
                                                    </td>
                                                    <td class="text-end font-monospace text-primary fw-semibold">
                                                        {{ format_currency((float) ($emp['hourly_salary_rate'] ?? $emp['hourly_rate'] ?? 0)) }}/hr
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ format_currency((float) ($emp['daily_cost'] ?? 0)) }}
                                                        @if(!empty($emp['formula']))
                                                            <i class="feather-info text-muted ms-1 cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $emp['formula'] }}"></i>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-3 text-muted fst-italic">
                                                        No active internal employees assigned to this section.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if(!empty($intLabor['employees']))
                                            <tfoot class="table-light border-top fw-bold">
                                                <tr>
                                                    <td>Total Internal:</td>
                                                    <td class="text-end font-monospace">{{ format_currency($intLabor['total_internal_monthly_salary'] ?? 0) }}</td>
                                                    <td class="text-end font-monospace">{{ format_currency($intLabor['total_internal_daily_salary'] ?? 0) }}</td>
                                                    <td></td>
                                                    <td class="text-end font-monospace text-primary">Avg {{ format_currency($avgInternalRate) }}/hr</td>
                                                    <td class="text-end font-monospace text-dark">{{ format_currency($dailyInternalCost) }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- External Contractors -->
                        <div class="card border rounded-3 mb-0 shadow-none">
                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                <span class="fw-bold fs-13 text-dark">
                                    <i class="feather-users text-primary me-1"></i>External Contractors (Hourly Staff)
                                </span>
                                <span class="text-muted fs-11">
                                    Contractors are paid for full shift hours on site (including lunch break) &bull; Multiple groups per shift supported
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 24%">Pay Grade & Group</th>
                                                <th style="width: 18%">Shift (Clock Hours)</th>
                                                <th style="width: 10%" class="text-center">Workers</th>
                                                <th style="width: 16%" class="text-end">Hourly Pay Grade</th>
                                                <th style="width: 16%" class="text-end">Group Burn / hr</th>
                                                <th style="width: 16%" class="text-end">
                                                    Daily Cost
                                                    <i class="feather-info text-muted ms-1 cursor-pointer" title="Daily Cost = Workers × Hourly Rate × Shift Clock Hours" data-bs-toggle="tooltip"></i>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($extLabor['allocations'] ?? [] as $alloc)
                                                <tr>
                                                    <td>
                                                        <strong class="text-dark d-block">{{ $alloc['pay_grade_name'] ?? 'Contractor' }}</strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info font-monospace">{{ $alloc['shift_name'] ?? 'N/A' }}</span>
                                                        <small class="text-muted d-block fs-11">{{ format_hours((float) ($alloc['shift_clock_hours'] ?? $alloc['shift_duration_hours'] ?? 0)) }}</small>
                                                    </td>
                                                    <td class="text-center font-monospace fw-bold text-primary">
                                                        {{ $alloc['headcount'] ?? 0 }}
                                                    </td>
                                                    <td class="text-end font-monospace text-muted">
                                                        {{ format_currency((float) ($alloc['pay_grade_rate'] ?? 0)) }}/hr
                                                    </td>
                                                    <td class="text-end font-monospace text-primary fw-semibold">
                                                        {{ format_currency((float) ($alloc['hourly_burn_rate'] ?? 0)) }}/hr
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ format_currency((float) ($alloc['daily_cost'] ?? 0)) }}
                                                        @if(!empty($alloc['formula']))
                                                            <i class="feather-info text-muted ms-1 cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $alloc['formula'] }}"></i>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-3 text-muted fst-italic">
                                                        No external contractor groups allocated to this section.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if(!empty($extLabor['allocations']))
                                            <tfoot class="table-light border-top fw-bold">
                                                <tr>
                                                    <td colspan="2">Total External Contractors:</td>
                                                    <td class="text-center font-monospace text-primary">{{ $extLabor['total_external_headcount'] ?? 0 }} workers</td>
                                                    <td></td>
                                                    <td class="text-end font-monospace text-primary">Avg {{ format_currency($avgExternalRate) }}/hr</td>
                                                    <td class="text-end font-monospace text-dark">{{ format_currency($dailyExternalCost) }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pane 4: Timeline Intervals & Overlap Slices -->
                    <div class="tab-pane fade" id="audit-timeline-pane-{{ $section->id }}" role="tabpanel">
                        <div class="alert alert-info py-2 px-3 fs-12 mb-3 d-flex align-items-center gap-2 rounded-3">
                            <i class="feather-info fs-15 text-info"></i>
                            <div>
                                The factory operating day is discretized into discrete timeline intervals at each shift boundary. When shifts overlap (e.g. 14:00 to 15:00), personnel and manual batch capacities operate concurrently, creating peak hourly output and surge labor burn rates.
                            </div>
                        </div>

                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 14%">Window</th>
                                        <th style="width: 8%">Duration</th>
                                        <th style="width: 14%">Running Capacity</th>
                                        <th style="width: 14%">Labor Burn / hr</th>
                                        <th style="width: 12%">Machine Opex</th>
                                        <th style="width: 12%">Total Cost / hr</th>
                                        <th style="width: 12%" class="text-end">Window Output</th>
                                        <th style="width: 14%" class="text-end">Window Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($timelineIntervals as $int)
                                        <tr class="{{ !empty($int['is_overlapping']) ? 'table-warning bg-soft-warning' : '' }}">
                                            <td>
                                                <span class="font-monospace fw-bold text-dark">{{ $int['window'] ?? 'N/A' }}</span>
                                                @if(!empty($int['is_overlapping']))
                                                    <span class="badge bg-warning text-dark fs-10 px-1.5 py-0.5 rounded-pill d-block mt-0.5" title="{{ $int['explanation'] ?? '' }}">
                                                        <i class="feather-layers me-0.5"></i>Overlap Surge
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="font-monospace text-muted">
                                                {{ format_hours((float) ($int['duration_hours'] ?? 0)) }}
                                            </td>
                                            <td class="font-monospace">
                                                <span class="text-success fw-bold">{{ number_format((float) ($int['running_total_capacity'] ?? 0), 1) }} u/h</span>
                                                <small class="text-muted d-block fs-10">M: {{ number_format((float) ($int['running_machine_capacity'] ?? 0), 0) }} + B: {{ number_format((float) ($int['running_manual_capacity'] ?? 0), 0) }}</small>
                                            </td>
                                            <td class="font-monospace">
                                                <span class="text-primary fw-bold">{{ format_currency((float) ($int['running_total_labor_rate'] ?? 0)) }}/h</span>
                                                <small class="text-muted d-block fs-10">Int: {{ format_currency((float) ($int['running_internal_labor_rate'] ?? 0)) }} + Ext: {{ format_currency((float) ($int['running_external_labor_rate'] ?? 0)) }}</small>
                                            </td>
                                            <td class="font-monospace text-dark">
                                                {{ format_currency((float) ($int['running_machine_rate'] ?? 0)) }}/h
                                            </td>
                                            <td class="font-monospace fw-bold text-danger">
                                                {{ format_currency((float) ($int['running_total_cost_rate'] ?? 0)) }}/h
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-success">
                                                {{ number_format((float) ($int['window_total_output'] ?? 0), 1) }} u
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-dark">
                                                {{ format_currency((float) ($int['window_total_cost'] ?? 0)) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-3 text-muted fst-italic">
                                                No timeline intervals generated.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($timelineIntervals))
                                    <tfoot class="table-light border-top fw-bold">
                                        <tr>
                                            <td>Total Day Window:</td>
                                            <td class="font-monospace text-primary">{{ format_hours($uniqHours) }}</td>
                                            <td class="font-monospace text-success">Avg {{ number_format($avgHourlyCap, 1) }} u/h</td>
                                            <td class="font-monospace text-primary">Avg {{ format_currency($avgLaborRate) }}/h</td>
                                            <td class="font-monospace">{{ format_currency($hourlyMachCost) }}/h</td>
                                            <td class="font-monospace text-danger">Avg {{ format_currency($avgHourlyCost) }}/h</td>
                                            <td class="text-end font-monospace text-success">{{ number_format($dailyCap, 1) }} u</td>
                                            <td class="text-end font-monospace text-dark">{{ format_currency($dailyCost) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                    <!-- Pane 5: All 52 Audit Metrics -->
                    <div class="tab-pane fade" id="audit-all-pane-{{ $section->id }}" role="tabpanel">
                        <div class="table-responsive border rounded-3" style="max-height: 440px; overflow-y: auto;">
                            <table class="table table-sm table-striped align-middle mb-0 fs-12">
                                <thead class="table-light position-sticky top-0 shadow-sm" style="z-index: 1;">
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 22%">Category</th>
                                        <th style="width: 38%">Metric Name</th>
                                        <th style="width: 35%" class="text-end">Current Computed Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $auditMetricsMeta = [
                                            1 => ['name' => 'Shifts Count', 'cat' => 'Operating Timeline', 'type' => 'Collected', 'source' => 'Section Shifts Configuration', 'formula' => 'Count of active assigned shifts', 'desc' => 'Total active production shifts scheduled for this section.'],
                                            2 => ['name' => 'Total Shift Clock Hours', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Section Shift Times', 'formula' => 'Sum of individual shift durations (End Time - Start Time)', 'desc' => 'Gross cumulative clock hours across all shifts before overlap elimination.'],
                                            3 => ['name' => 'Unique Operating Hours', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Elapsed operating hours after merging all overlapping shift intervals', 'desc' => 'Actual unique hours the section is active within the 24-hour day.'],
                                            4 => ['name' => 'Shift Overlap Hours', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Total Shift Clock Hours - Unique Operating Hours', 'desc' => 'Hours during which multiple shifts run simultaneously.'],
                                            5 => ['name' => 'Has Overlapping Shifts', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Shift Overlap Hours > 0', 'desc' => 'Indicates whether any scheduled shifts share common clock hours.'],
                                            6 => ['name' => 'Operational Window Start', 'cat' => 'Operating Timeline', 'type' => 'Collected', 'source' => 'Shift Schedule Registry', 'formula' => 'Earliest start time among all active shifts', 'desc' => 'Clock time when this section begins operating for the day.'],
                                            7 => ['name' => 'Operational Window End', 'cat' => 'Operating Timeline', 'type' => 'Collected', 'source' => 'Shift Schedule Registry', 'formula' => 'Latest end time among all active shifts', 'desc' => 'Clock time when this section finishes daily operations.'],
                                            8 => ['name' => 'Has Inactive Gaps', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Inactive Gap Hours > 0 within operating window', 'desc' => 'Indicates whether there are unstaffed idle gaps between shifts.'],
                                            9 => ['name' => 'Inactive Gap Hours', 'cat' => 'Operating Timeline', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => '(Operational Window Duration) - (Unique Operating Hours)', 'desc' => 'Total idle clock hours between scheduled shifts.'],

                                            10 => ['name' => 'Active Machines Count', 'cat' => 'Machinery', 'type' => 'Collected', 'source' => 'Section Machine Assignments', 'formula' => 'Count of active assigned machines', 'desc' => 'Total operational machines currently assigned to this section.'],
                                            11 => ['name' => 'Inactive Machines Count', 'cat' => 'Machinery', 'type' => 'Collected', 'source' => 'Section Machine Assignments', 'formula' => 'Count of disabled / offline assigned machines', 'desc' => 'Machines assigned to this section that are temporarily inactive.'],
                                            12 => ['name' => 'Hourly Machine Capacity', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machine Engineering Specifications', 'formula' => 'Sum of hourly rated capacities of all active machines', 'desc' => 'Combined output capacity in units produced per running machine hour.'],
                                            13 => ['name' => 'Daily Machine Capacity', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machine Hourly Capacity & Shift Hours', 'formula' => 'Sum of (Active Machine Hourly Capacity * Operating Shift Hours)', 'desc' => 'Total units produced by machinery across all scheduled shifts.'],
                                            14 => ['name' => 'Sum Energy Consumption / hr', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machine Electrical Ratings', 'formula' => 'Sum of power consumption (kW) across all active machines', 'desc' => 'Total electrical power consumed per hour by all running machines.'],
                                            15 => ['name' => 'Calculated Base Cost / hr', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machine Energy & Electricity Tariffs', 'formula' => 'Sum of (Machine Power kW * Electricity Cost per kWh)', 'desc' => 'Base electricity and fuel running expense per hour.'],
                                            16 => ['name' => 'Total Additional Cost / hr', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machine Maintenance & Consumables Rates', 'formula' => 'Sum of hourly consumables, tooling, and maintenance charges', 'desc' => 'Additional hourly operating expenses beyond electricity.'],
                                            17 => ['name' => 'Total Deductibles / hr', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machine Subsidies & Energy Credits', 'formula' => 'Sum of hourly credits, offsets, or operational rebates', 'desc' => 'Hourly financial deductions applied to machine operation.'],
                                            18 => ['name' => 'Hourly Machine Expense', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machinery Cost Engine', 'formula' => 'Base Cost/hr + Additional Cost/hr - Deductibles/hr', 'desc' => 'Net total operating expense per hour for all active machines.'],
                                            19 => ['name' => 'Total Daily Machine Expense', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machinery Cost Engine & Shift Intervals', 'formula' => 'Sum of (Hourly Machine Expense * Shift Operating Hours)', 'desc' => 'Total expense incurred to run machinery for the entire day.'],
                                            20 => ['name' => 'Machine Cost Per Unit', 'cat' => 'Machinery', 'type' => 'Calculated', 'source' => 'Machinery Cost & Capacity Engine', 'formula' => 'Total Daily Machine Expense / Daily Machine Capacity', 'desc' => 'Direct machinery cost allocated to each machine-produced unit.'],

                                            21 => ['name' => 'Active Manual Batches', 'cat' => 'Manual Batches', 'type' => 'Collected', 'source' => 'Manual Batch Configurations', 'formula' => 'Count of active manual production setups', 'desc' => 'Number of operational manual stations/lines configured in this section.'],
                                            22 => ['name' => 'Inactive Manual Batches', 'cat' => 'Manual Batches', 'type' => 'Collected', 'source' => 'Manual Batch Configurations', 'formula' => 'Count of disabled manual production setups', 'desc' => 'Manual stations currently deactivated or on hold.'],
                                            23 => ['name' => 'Daily Manual Capacity', 'cat' => 'Manual Batches', 'type' => 'Calculated', 'source' => 'Manual Batch Hourly Rates & Shift Hours', 'formula' => 'Sum of (Manual Batch Speed/hr * Assigned Shift Duration Hours)', 'desc' => 'Total units produced manually across all scheduled shifts.'],
                                            24 => ['name' => 'Hourly Manual Capacity [Weighted Avg]', 'cat' => 'Manual Batches', 'type' => 'Calculated', 'source' => 'Manual Production Engine', 'formula' => 'Daily Manual Capacity / Section Unique Operating Hours', 'desc' => 'Average manual output per hour across the section operating window.'],
                                            25 => ['name' => 'Peak Hourly Manual Capacity', 'cat' => 'Manual Batches', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Maximum manual throughput achieved in any single clock interval', 'desc' => 'Peak manual production speed when multiple manual shifts overlap.'],
                                            26 => ['name' => 'Shift Manual Capacities Subtotals', 'cat' => 'Manual Batches', 'type' => 'Calculated', 'source' => 'Shift Roster & Batch Allocations', 'formula' => 'Breakdown of manual capacity generated within each specific shift', 'desc' => 'Subtotal of manual units produced mapped per shift.'],

                                            27 => ['name' => 'Internal Employees Count', 'cat' => 'Internal Labor', 'type' => 'Collected', 'source' => 'HRMS Employee Master', 'formula' => 'Count of salaried internal employees assigned to this section', 'desc' => 'Total company personnel assigned to work in this section.'],
                                            28 => ['name' => 'Total Monthly Salary', 'cat' => 'Internal Labor', 'type' => 'Collected', 'source' => 'HRMS Payroll (CTC)', 'formula' => 'Sum of monthly salaries (CTC) of all assigned internal employees', 'desc' => 'Combined gross monthly payroll expense for section employees.'],
                                            29 => ['name' => 'Total Daily Salary [CTC/30]', 'cat' => 'Internal Labor', 'type' => 'Calculated', 'source' => 'Statutory Daily Payroll Proration', 'formula' => 'Total Monthly Salary / 30 calendar days', 'desc' => 'Daily internal payroll burn based on standard 30-day month.'],
                                            30 => ['name' => 'Total Daily Internal Cost', 'cat' => 'Internal Labor', 'type' => 'Calculated', 'source' => 'Internal Labor Engine', 'formula' => 'Sum of allocated daily salaries across assigned section shifts', 'desc' => 'Total daily internal employee payroll allocated to this section.'],
                                            31 => ['name' => 'Average Internal Hourly Rate', 'cat' => 'Internal Labor', 'type' => 'Calculated', 'source' => 'Internal Labor Engine', 'formula' => 'Total Daily Internal Cost / Unique Operating Hours', 'desc' => 'Average internal wage expense per operating hour.'],
                                            32 => ['name' => 'Shift Internal Hourly Rates', 'cat' => 'Internal Labor', 'type' => 'Calculated', 'source' => 'Shift Staffing Allocation', 'formula' => 'Breakdown of internal hourly wages incurred during each shift', 'desc' => 'Hourly internal payroll burn mapped to individual shifts.'],
                                            33 => ['name' => 'Peak Internal Hourly Rate', 'cat' => 'Internal Labor', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Maximum internal wage expense during peak shift overlap', 'desc' => 'Highest internal labor expense per hour when shifts overlap.'],
                                            34 => ['name' => 'Internal Labor Cost Per Unit', 'cat' => 'Internal Labor', 'type' => 'Calculated', 'source' => 'Labor & Capacity Engine', 'formula' => 'Total Daily Internal Cost / Total Daily Capacity', 'desc' => 'Internal salaried labor cost allocated per unit produced.'],

                                            35 => ['name' => 'External Allocations Count', 'cat' => 'External Labor', 'type' => 'Collected', 'source' => 'External Worker Pay Grade Allocations', 'formula' => 'Count of active external contractor allocation records', 'desc' => 'Number of contractor labor allocation groups assigned to shifts.'],
                                            36 => ['name' => 'Total External Headcount', 'cat' => 'External Labor', 'type' => 'Collected', 'source' => 'External Worker Rostering', 'formula' => 'Sum of contractor worker counts assigned across shifts', 'desc' => 'Total external contractor workers deployed across all shifts.'],
                                            37 => ['name' => 'Total Daily External Cost', 'cat' => 'External Labor', 'type' => 'Calculated', 'source' => 'External Labor Engine', 'formula' => 'Sum of (Contractor Workers * Hourly Pay Grade * Shift Hours)', 'desc' => 'Total daily contractor wages incurred across all shifts.'],
                                            38 => ['name' => 'Average External Hourly Rate', 'cat' => 'External Labor', 'type' => 'Calculated', 'source' => 'External Labor Engine', 'formula' => 'Total Daily External Cost / Unique Operating Hours', 'desc' => 'Average contractor wage expense per running hour.'],
                                            39 => ['name' => 'Shift External Hourly Rates', 'cat' => 'External Labor', 'type' => 'Calculated', 'source' => 'Shift External Allocations', 'formula' => 'Sum of (Workers in Shift * Hourly Pay Grade Rate)', 'desc' => 'Contractor hourly burn rate mapped to each individual shift.'],
                                            40 => ['name' => 'Peak External Hourly Rate', 'cat' => 'External Labor', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Maximum contractor expense in any single operating interval', 'desc' => 'Highest contractor labor expense per hour during peak shift overlap.'],
                                            41 => ['name' => 'External Labor Cost Per Unit', 'cat' => 'External Labor', 'type' => 'Calculated', 'source' => 'Labor & Capacity Engine', 'formula' => 'Total Daily External Cost / Total Daily Capacity', 'desc' => 'Contractor labor expense allocated to each unit produced.'],

                                            42 => ['name' => 'Total Daily Labor Cost', 'cat' => 'Combined Labor', 'type' => 'Calculated', 'source' => 'Labor Cost Engine', 'formula' => 'Total Daily Internal Cost + Total Daily External Cost', 'desc' => 'Combined daily expense for all salaried and hourly personnel.'],
                                            43 => ['name' => 'Total Average Hourly Labor Rate', 'cat' => 'Combined Labor', 'type' => 'Calculated', 'source' => 'Labor Cost Engine', 'formula' => 'Total Daily Labor Cost / Unique Operating Hours', 'desc' => 'Blended workforce expense per operating hour throughout the day.'],
                                            44 => ['name' => 'Peak Hourly Labor Rate', 'cat' => 'Combined Labor', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Highest combined labor burn rate across all timeline intervals', 'desc' => 'Maximum total labor expense per hour during peak staffing overlap.'],
                                            45 => ['name' => 'Total Labor Cost Per Unit', 'cat' => 'Combined Labor', 'type' => 'Calculated', 'source' => 'Labor & Capacity Engine', 'formula' => 'Total Daily Labor Cost / Total Daily Capacity', 'desc' => 'Total labor expense (internal + external) absorbed per unit produced.'],

                                            46 => ['name' => 'Total Daily Capacity', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Production Capacity Engine', 'formula' => 'Daily Machine Capacity + Daily Manual Capacity', 'desc' => 'Total combined units produced per day by machines and manual stations.'],
                                            47 => ['name' => 'Total Hourly Capacity [Weighted Avg]', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Production Capacity Engine', 'formula' => 'Total Daily Capacity / Unique Operating Hours', 'desc' => 'Average production output per hour across the section operating window.'],
                                            48 => ['name' => 'Peak Total Hourly Capacity', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Maximum combined hourly output across all operating intervals', 'desc' => 'Peak units per hour when maximum machines and manual stations overlap.'],
                                            49 => ['name' => 'Total Daily Operating Cost', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Financial Roll-Up Engine', 'formula' => 'Total Daily Labor Cost + Total Daily Machine Expense', 'desc' => 'Total direct daily operational cost to run this production section.'],
                                            50 => ['name' => 'Total Hourly Operating Cost [Weighted Avg]', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Financial Roll-Up Engine', 'formula' => 'Total Daily Operating Cost / Unique Operating Hours', 'desc' => 'Average operational burn rate per hour across the operating day.'],
                                            51 => ['name' => 'Peak Total Hourly Operating Cost', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Timeline Interval Engine', 'formula' => 'Highest combined hourly burn rate across all timeline intervals', 'desc' => 'Peak hourly operating cost during maximum machine and staffing overlap.'],
                                            52 => ['name' => 'Cost Per Unit', 'cat' => 'Financial Summary', 'type' => 'Calculated', 'source' => 'Financial Roll-Up Engine', 'formula' => 'Total Daily Operating Cost / Total Daily Capacity', 'desc' => 'Total direct manufacturing cost required to produce one finished unit.'],
                                        ];

                                        $renderAuditMetricRow = function($idx, $valHtml) use ($auditMetricsMeta) {
                                            $meta = $auditMetricsMeta[$idx] ?? [
                                                'name' => 'Metric #' . $idx,
                                                'cat' => 'General',
                                                'type' => 'Calculated',
                                                'source' => 'Calculation Engine',
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

                                            return '<tr>'
                                                . '<td class="text-muted">' . $idx . '</td>'
                                                . '<td class="text-muted">' . e($meta['cat']) . '</td>'
                                                . '<td>'
                                                . '<div class="d-inline-flex align-items-center">'
                                                . '<span>' . e($meta['name']) . '</span>'
                                                . '<a href="javascript:void(0)" class="text-primary ms-1 text-decoration-none audit-metric-popover-trigger" ' . $popoverData . ' tabindex="0" role="button" aria-label="Details for ' . e($meta['name']) . '">'
                                                . '<i class="feather-info" style="font-size: 13px; vertical-align: middle;"></i>'
                                                . '</a>'
                                                . '</div>'
                                                . '</td>'
                                                . '<td class="text-end font-monospace">' . $valHtml . '</td>'
                                                . '</tr>';
                                        };
                                    @endphp

                                    <!-- Category 1: Operating Timeline -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">1. Operating Timeline & Shift Windows</td></tr>
                                    {!! $renderAuditMetricRow(1, ($opTimeline['shifts_count'] ?? 0) . ' shifts') !!}
                                    {!! $renderAuditMetricRow(2, format_hours($opTimeline['total_shift_clock_hours'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(3, '<span class="fw-bold text-primary">' . format_hours($uniqHours) . '</span>') !!}
                                    {!! $renderAuditMetricRow(4, '<span class="text-warning">' . format_hours($overlapHours) . '</span>') !!}
                                    {!! $renderAuditMetricRow(5, $hasOverlap ? '<span class="badge bg-soft-warning text-warning">Yes</span>' : '<span class="badge bg-soft-success text-success">No</span>') !!}
                                    {!! $renderAuditMetricRow(6, $opTimeline['operational_window_start'] ?? 'None') !!}
                                    {!! $renderAuditMetricRow(7, $opTimeline['operational_window_end'] ?? 'None') !!}
                                    {!! $renderAuditMetricRow(8, $hasGaps ? '<span class="badge bg-soft-warning text-warning">Yes</span>' : '<span class="badge bg-soft-success text-success">No</span>') !!}
                                    {!! $renderAuditMetricRow(9, format_hours($gapHours)) !!}

                                    <!-- Category 2: Machinery -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">2. Machinery Capacity & Operating Cost</td></tr>
                                    {!! $renderAuditMetricRow(10, (string) ($machData['active_machines_count'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(11, (string) ($machData['inactive_machines_count'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(12, '<span class="text-success">' . number_format($machData['hourly_machine_capacity'] ?? 0, 1) . ' u/h</span>') !!}
                                    {!! $renderAuditMetricRow(13, '<span class="text-success fw-bold">' . number_format($dailyMachCap, 1) . ' units</span>') !!}
                                    {!! $renderAuditMetricRow(14, number_format($machData['sum_machine_consumption_per_hour'] ?? 0, 2) . ' kW') !!}
                                    {!! $renderAuditMetricRow(15, format_currency($machData['calculated_machine_base_cost_per_hour'] ?? 0) . '/hr') !!}
                                    {!! $renderAuditMetricRow(16, '<span class="text-warning">+' . format_currency($machData['total_machine_additional_cost_per_hour'] ?? 0) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(17, '<span class="text-success">-' . format_currency($machData['total_machine_deductibles_per_hour'] ?? 0) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(18, '<span class="text-primary">' . format_currency($hourlyMachCost) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(19, '<span class="fw-bold text-dark">' . format_currency($dailyMachCost) . '</span>') !!}
                                    {!! $renderAuditMetricRow(20, format_currency($machData['machine_cost_per_unit'] ?? 0) . ' / unit') !!}

                                    <!-- Category 3: Manual Batches -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">3. Manual Batch Production</td></tr>
                                    {!! $renderAuditMetricRow(21, (string) ($batchData['active_manual_batches_count'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(22, (string) ($batchData['inactive_manual_batches_count'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(23, '<span class="text-success fw-bold">' . number_format($dailyManualCap, 1) . ' units</span>') !!}
                                    {!! $renderAuditMetricRow(24, '<span class="text-success">' . number_format($hourlyManualCap, 1) . ' u/h</span>') !!}
                                    {!! $renderAuditMetricRow(25, '<span class="text-primary">' . number_format($batchData['peak_hourly_manual_capacity'] ?? 0, 1) . ' u/h</span>') !!}
                                    {!! $renderAuditMetricRow(26, count($batchData['shift_manual_capacities'] ?? []) . ' shifts mapped') !!}

                                    <!-- Category 4: Internal Labor -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">4. Internal Employee Labor (Salaried Staff)</td></tr>
                                    {!! $renderAuditMetricRow(27, (string) ($intLabor['internal_employees_count'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(28, format_currency($intLabor['total_internal_monthly_salary'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(29, format_currency($intLabor['total_internal_daily_salary'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(30, '<span class="fw-bold text-dark">' . format_currency($dailyInternalCost) . '</span>') !!}
                                    {!! $renderAuditMetricRow(31, '<span class="text-primary">' . format_currency($avgInternalRate) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(32, count($intLabor['shift_internal_hourly_rates'] ?? []) . ' shifts mapped') !!}
                                    {!! $renderAuditMetricRow(33, '<span class="text-danger">' . format_currency($intLabor['peak_internal_hourly_rate'] ?? 0) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(34, format_currency($intLabor['internal_labor_cost_per_unit'] ?? 0) . ' / unit') !!}

                                    <!-- Category 5: External Labor -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">5. External Contractor Labor (Hourly Staff)</td></tr>
                                    {!! $renderAuditMetricRow(35, (string) ($extLabor['external_allocations_count'] ?? 0)) !!}
                                    {!! $renderAuditMetricRow(36, ($extLabor['total_external_headcount'] ?? 0) . ' workers') !!}
                                    {!! $renderAuditMetricRow(37, '<span class="fw-bold text-dark">' . format_currency($dailyExternalCost) . '</span>') !!}
                                    {!! $renderAuditMetricRow(38, '<span class="text-primary">' . format_currency($avgExternalRate) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(39, count($extLabor['shift_external_hourly_rates'] ?? []) . ' shifts mapped') !!}
                                    {!! $renderAuditMetricRow(40, '<span class="text-danger">' . format_currency($extLabor['peak_external_hourly_rate'] ?? 0) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(41, format_currency($extLabor['external_labor_cost_per_unit'] ?? 0) . ' / unit') !!}

                                    <!-- Category 6: Combined Labor -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">6. Combined Labor Metrics</td></tr>
                                    {!! $renderAuditMetricRow(42, '<span class="fw-bold text-primary">' . format_currency($dailyLaborCost) . '</span>') !!}
                                    {!! $renderAuditMetricRow(43, format_currency($avgLaborRate) . '/hr') !!}
                                    {!! $renderAuditMetricRow(44, '<span class="text-danger fw-bold">' . format_currency($peakLaborRate) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(45, format_currency($combLabor['total_labor_cost_per_unit'] ?? 0) . ' / unit') !!}

                                    <!-- Category 7: Financial Summary -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">7. Total Financial & Production Summary</td></tr>
                                    {!! $renderAuditMetricRow(46, '<span class="text-success fw-bold">' . number_format($dailyCap, 1) . ' units</span>') !!}
                                    {!! $renderAuditMetricRow(47, '<span class="text-success">' . number_format($avgHourlyCap, 1) . ' u/h</span>') !!}
                                    {!! $renderAuditMetricRow(48, '<span class="text-primary fw-bold">' . number_format($peakHourlyCap, 1) . ' u/h</span>') !!}
                                    {!! $renderAuditMetricRow(49, '<span class="text-primary fw-bolder">' . format_currency($dailyCost) . '</span>') !!}
                                    {!! $renderAuditMetricRow(50, format_currency($avgHourlyCost) . '/hr') !!}
                                    {!! $renderAuditMetricRow(51, '<span class="text-danger fw-bold">' . format_currency($peakHourlyCost) . '/hr</span>') !!}
                                    {!! $renderAuditMetricRow(52, '<span class="text-dark fw-bolder">' . format_currency($costPerUnit) . ' / unit</span>') !!}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    {{ __('production.close') ?? 'Close' }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
if (!window.batchCollapseSyncInitialized) {
    window.batchCollapseSyncInitialized = true;
    $(document).on('show.bs.collapse', '.batch-collapse-pane', function () {
        var id = $(this).attr('id');
        $('[data-bs-target="#' + id + '"]').attr('aria-expanded', 'true');
    });
    $(document).on('hide.bs.collapse', '.batch-collapse-pane', function () {
        var id = $(this).attr('id');
        $('[data-bs-target="#' + id + '"]').attr('aria-expanded', 'false');
    });
}
</script>
