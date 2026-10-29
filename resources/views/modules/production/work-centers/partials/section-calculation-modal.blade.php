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
                        <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="sectionCalculationModalLabel_{{ $section->id }}">
                            {{ __('production.calculation_breakdown') }} &mdash; <span class="font-monospace text-primary">{{ $section->code }}</span> ({{ $section->name }})
                        </h5>
                        <small class="text-muted fs-11">{{ __('production.work_center') }}: {{ $workCenter->name }} [{{ $workCenter->code }}]</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                @php
                    $secCalc = $section->latestCalculation;
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

                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 20%">Batch Name</th>
                                        <th style="width: 15%">Assigned Shift</th>
                                        <th style="width: 12%" class="text-end">Hourly Capacity</th>
                                        <th style="width: 12%" class="text-end">Shift Eff. Hours</th>
                                        <th style="width: 14%" class="text-end">Daily Output</th>
                                        <th style="width: 27%">Calculation Formula (English)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($batchData['batches'] ?? [] as $b)
                                        <tr>
                                            <td>
                                                <strong class="text-dark d-block">{{ $b['batch_name'] ?? $b['name'] ?? 'Manual Batch' }}</strong>
                                                <small class="text-muted">{{ count($b['team_members'] ?? $b['assigned_employees'] ?? []) }} internal emps</small>
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
                                            </td>
                                            <td class="font-monospace text-muted fs-11">
                                                {{ $b['formula'] ?? '' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-3 text-muted fst-italic">
                                                No active manual production batches configured in this section.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($batchData['batches']))
                                    <tfoot class="table-light border-top fw-bold">
                                        <tr>
                                            <td colspan="2">Total Manual Production:</td>
                                            <td class="text-end font-monospace text-success">Avg {{ number_format($hourlyManualCap, 1) }} u/h</td>
                                            <td></td>
                                            <td class="text-end font-monospace text-dark">{{ number_format($dailyManualCap, 1) }} units/day</td>
                                            <td></td>
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
                                                <th style="width: 18%">Employee</th>
                                                <th style="width: 14%" class="text-end">Monthly CTC</th>
                                                <th style="width: 14%" class="text-end">Daily Salary (CTC/30)</th>
                                                <th style="width: 14%">Shift (Clock Hours)</th>
                                                <th style="width: 14%" class="text-end">Hourly Salary Rate</th>
                                                <th style="width: 12%" class="text-end">Daily Cost</th>
                                                <th style="width: 14%">Formula</th>
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
                                                    </td>
                                                    <td class="font-monospace text-muted fs-11">
                                                        {{ $emp['formula'] ?? '' }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center py-3 text-muted fst-italic">
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
                                                    <td></td>
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
                                                <th style="width: 20%">Pay Grade & Group</th>
                                                <th style="width: 14%">Shift (Clock Hours)</th>
                                                <th style="width: 10%" class="text-center">Workers</th>
                                                <th style="width: 14%" class="text-end">Hourly Pay Grade</th>
                                                <th style="width: 14%" class="text-end">Group Burn / hr</th>
                                                <th style="width: 14%" class="text-end">Daily Cost</th>
                                                <th style="width: 14%">Formula</th>
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
                                                    </td>
                                                    <td class="font-monospace text-muted fs-11">
                                                        {{ $alloc['formula'] ?? '' }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center py-3 text-muted fst-italic">
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
                                                    <td></td>
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
                                        <th style="width: 25%">Category</th>
                                        <th style="width: 35%">Metric Name</th>
                                        <th style="width: 35%" class="text-end">Current Computed Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Category 1: Operating Timeline -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">1. Operating Timeline & Shift Windows</td></tr>
                                    <tr><td>1</td><td>Operating Timeline</td><td>Shifts Count</td><td class="text-end font-monospace">{{ $opTimeline['shifts_count'] ?? 0 }} shifts</td></tr>
                                    <tr><td>2</td><td>Operating Timeline</td><td>Total Shift Clock Hours</td><td class="text-end font-monospace">{{ format_hours($opTimeline['total_shift_clock_hours'] ?? 0) }}</td></tr>
                                    <tr><td>3</td><td>Operating Timeline</td><td>Unique Operating Hours</td><td class="text-end font-monospace fw-bold text-primary">{{ format_hours($uniqHours) }}</td></tr>
                                    <tr><td>4</td><td>Operating Timeline</td><td>Shift Overlap Hours</td><td class="text-end font-monospace text-warning">{{ format_hours($overlapHours) }}</td></tr>
                                    <tr><td>5</td><td>Operating Timeline</td><td>Has Overlapping Shifts</td><td class="text-end font-monospace">{{ $hasOverlap ? 'Yes' : 'No' }}</td></tr>
                                    <tr><td>6</td><td>Operating Timeline</td><td>Operational Window Start</td><td class="text-end font-monospace">{{ $opTimeline['operational_window_start'] ?? 'None' }}</td></tr>
                                    <tr><td>7</td><td>Operating Timeline</td><td>Operational Window End</td><td class="text-end font-monospace">{{ $opTimeline['operational_window_end'] ?? 'None' }}</td></tr>
                                    <tr><td>8</td><td>Operating Timeline</td><td>Has Inactive Gaps</td><td class="text-end font-monospace">{{ $hasGaps ? 'Yes' : 'No' }}</td></tr>
                                    <tr><td>9</td><td>Operating Timeline</td><td>Inactive Gap Hours</td><td class="text-end font-monospace">{{ format_hours($gapHours) }}</td></tr>

                                    <!-- Category 2: Machinery -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">2. Machinery Capacity & Operating Cost</td></tr>
                                    <tr><td>10</td><td>Machinery</td><td>Active Machines Count</td><td class="text-end font-monospace">{{ $machData['active_machines_count'] ?? 0 }}</td></tr>
                                    <tr><td>11</td><td>Machinery</td><td>Inactive Machines Count</td><td class="text-end font-monospace">{{ $machData['inactive_machines_count'] ?? 0 }}</td></tr>
                                    <tr><td>12</td><td>Machinery</td><td>Hourly Machine Capacity</td><td class="text-end font-monospace text-success">{{ number_format($machData['hourly_machine_capacity'] ?? 0, 1) }} u/h</td></tr>
                                    <tr><td>13</td><td>Machinery</td><td>Daily Machine Capacity</td><td class="text-end font-monospace text-success fw-bold">{{ number_format($dailyMachCap, 1) }} units</td></tr>
                                    <tr><td>14</td><td>Machinery</td><td>Sum Energy Consumption / hr</td><td class="text-end font-monospace">{{ number_format($machData['sum_machine_consumption_per_hour'] ?? 0, 2) }} kW</td></tr>
                                    <tr><td>15</td><td>Machinery</td><td>Calculated Base Cost / hr</td><td class="text-end font-monospace">{{ format_currency($machData['calculated_machine_base_cost_per_hour'] ?? 0) }}/hr</td></tr>
                                    <tr><td>16</td><td>Machinery</td><td>Total Additional Cost / hr</td><td class="text-end font-monospace text-warning">+{{ format_currency($machData['total_machine_additional_cost_per_hour'] ?? 0) }}/hr</td></tr>
                                    <tr><td>17</td><td>Machinery</td><td>Total Deductibles / hr</td><td class="text-end font-monospace text-success">-{{ format_currency($machData['total_machine_deductibles_per_hour'] ?? 0) }}/hr</td></tr>
                                    <tr><td>18</td><td>Machinery</td><td>Hourly Machine Expense</td><td class="text-end font-monospace text-primary">{{ format_currency($hourlyMachCost) }}/hr</td></tr>
                                    <tr><td>19</td><td>Machinery</td><td>Total Daily Machine Expense</td><td class="text-end font-monospace fw-bold text-dark">{{ format_currency($dailyMachCost) }}</td></tr>
                                    <tr><td>20</td><td>Machinery</td><td>Machine Cost Per Unit</td><td class="text-end font-monospace">{{ format_currency($machData['machine_cost_per_unit'] ?? 0) }} / unit</td></tr>

                                    <!-- Category 3: Manual Batches -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">3. Manual Batch Production</td></tr>
                                    <tr><td>21</td><td>Manual Batches</td><td>Active Manual Batches</td><td class="text-end font-monospace">{{ $batchData['active_manual_batches_count'] ?? 0 }}</td></tr>
                                    <tr><td>22</td><td>Manual Batches</td><td>Inactive Manual Batches</td><td class="text-end font-monospace">{{ $batchData['inactive_manual_batches_count'] ?? 0 }}</td></tr>
                                    <tr><td>23</td><td>Manual Batches</td><td>Daily Manual Capacity</td><td class="text-end font-monospace text-success fw-bold">{{ number_format($dailyManualCap, 1) }} units</td></tr>
                                    <tr><td>24</td><td>Manual Batches</td><td>Hourly Manual Capacity [Weighted Avg]</td><td class="text-end font-monospace text-success">{{ number_format($hourlyManualCap, 1) }} u/h</td></tr>
                                    <tr><td>25</td><td>Manual Batches</td><td>Peak Hourly Manual Capacity</td><td class="text-end font-monospace text-primary">{{ number_format($batchData['peak_hourly_manual_capacity'] ?? 0, 1) }} u/h</td></tr>
                                    <tr><td>26</td><td>Manual Batches</td><td>Shift Manual Capacities Subtotals</td><td class="text-end font-monospace">{{ count($batchData['shift_manual_capacities'] ?? []) }} shifts mapped</td></tr>

                                    <!-- Category 4: Internal Labor -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">4. Internal Employee Labor (Salaried Staff)</td></tr>
                                    <tr><td>27</td><td>Internal Labor</td><td>Internal Employees Count</td><td class="text-end font-monospace">{{ $intLabor['internal_employees_count'] ?? 0 }}</td></tr>
                                    <tr><td>28</td><td>Internal Labor</td><td>Total Monthly Salary</td><td class="text-end font-monospace">{{ format_currency($intLabor['total_internal_monthly_salary'] ?? 0) }}</td></tr>
                                    <tr><td>29</td><td>Internal Labor</td><td>Total Daily Salary [CTC/30]</td><td class="text-end font-monospace">{{ format_currency($intLabor['total_internal_daily_salary'] ?? 0) }}</td></tr>
                                    <tr><td>30</td><td>Internal Labor</td><td>Total Daily Internal Cost</td><td class="text-end font-monospace fw-bold text-dark">{{ format_currency($dailyInternalCost) }}</td></tr>
                                    <tr><td>31</td><td>Internal Labor</td><td>Average Internal Hourly Rate</td><td class="text-end font-monospace text-primary">{{ format_currency($avgInternalRate) }}/hr</td></tr>
                                    <tr><td>32</td><td>Internal Labor</td><td>Shift Internal Hourly Rates</td><td class="text-end font-monospace">{{ count($intLabor['shift_internal_hourly_rates'] ?? []) }} shifts mapped</td></tr>
                                    <tr><td>33</td><td>Internal Labor</td><td>Peak Internal Hourly Rate</td><td class="text-end font-monospace text-danger">{{ format_currency($intLabor['peak_internal_hourly_rate'] ?? 0) }}/hr</td></tr>
                                    <tr><td>34</td><td>Internal Labor</td><td>Internal Labor Cost Per Unit</td><td class="text-end font-monospace">{{ format_currency($intLabor['internal_labor_cost_per_unit'] ?? 0) }} / unit</td></tr>

                                    <!-- Category 5: External Labor -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">5. External Contractor Labor (Hourly Staff)</td></tr>
                                    <tr><td>35</td><td>External Labor</td><td>External Allocations Count</td><td class="text-end font-monospace">{{ $extLabor['external_allocations_count'] ?? 0 }}</td></tr>
                                    <tr><td>36</td><td>External Labor</td><td>Total External Headcount</td><td class="text-end font-monospace">{{ $extLabor['total_external_headcount'] ?? 0 }} workers</td></tr>
                                    <tr><td>37</td><td>External Labor</td><td>Total Daily External Cost</td><td class="text-end font-monospace fw-bold text-dark">{{ format_currency($dailyExternalCost) }}</td></tr>
                                    <tr><td>38</td><td>External Labor</td><td>Average External Hourly Rate</td><td class="text-end font-monospace text-primary">{{ format_currency($avgExternalRate) }}/hr</td></tr>
                                    <tr><td>39</td><td>External Labor</td><td>Shift External Hourly Rates</td><td class="text-end font-monospace">{{ count($extLabor['shift_external_hourly_rates'] ?? []) }} shifts mapped</td></tr>
                                    <tr><td>40</td><td>External Labor</td><td>Peak External Hourly Rate</td><td class="text-end font-monospace text-danger">{{ format_currency($extLabor['peak_external_hourly_rate'] ?? 0) }}/hr</td></tr>
                                    <tr><td>41</td><td>External Labor</td><td>External Labor Cost Per Unit</td><td class="text-end font-monospace">{{ format_currency($extLabor['external_labor_cost_per_unit'] ?? 0) }} / unit</td></tr>

                                    <!-- Category 6: Combined Labor -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">6. Combined Labor Metrics</td></tr>
                                    <tr><td>42</td><td>Combined Labor</td><td>Total Daily Labor Cost</td><td class="text-end font-monospace fw-bold text-primary">{{ format_currency($dailyLaborCost) }}</td></tr>
                                    <tr><td>43</td><td>Combined Labor</td><td>Total Average Hourly Labor Rate</td><td class="text-end font-monospace">{{ format_currency($avgLaborRate) }}/hr</td></tr>
                                    <tr><td>44</td><td>Combined Labor</td><td>Peak Hourly Labor Rate</td><td class="text-end font-monospace text-danger fw-bold">{{ format_currency($peakLaborRate) }}/hr</td></tr>
                                    <tr><td>45</td><td>Combined Labor</td><td>Total Labor Cost Per Unit</td><td class="text-end font-monospace">{{ format_currency($combLabor['total_labor_cost_per_unit'] ?? 0) }} / unit</td></tr>

                                    <!-- Category 7: Financial Summary -->
                                    <tr class="table-secondary fw-bold"><td colspan="4">7. Total Financial & Production Summary</td></tr>
                                    <tr><td>46</td><td>Financial Summary</td><td>Total Daily Capacity</td><td class="text-end font-monospace text-success fw-bold">{{ number_format($dailyCap, 1) }} units</td></tr>
                                    <tr><td>47</td><td>Financial Summary</td><td>Total Hourly Capacity [Weighted Avg]</td><td class="text-end font-monospace text-success">{{ number_format($avgHourlyCap, 1) }} u/h</td></tr>
                                    <tr><td>48</td><td>Financial Summary</td><td>Peak Total Hourly Capacity</td><td class="text-end font-monospace text-primary fw-bold">{{ number_format($peakHourlyCap, 1) }} u/h</td></tr>
                                    <tr><td>49</td><td>Financial Summary</td><td>Total Daily Operating Cost</td><td class="text-end font-monospace text-primary fw-bolder">{{ format_currency($dailyCost) }}</td></tr>
                                    <tr><td>50</td><td>Financial Summary</td><td>Total Hourly Operating Cost [Weighted Avg]</td><td class="text-end font-monospace">{{ format_currency($avgHourlyCost) }}/hr</td></tr>
                                    <tr><td>51</td><td>Financial Summary</td><td>Peak Total Hourly Operating Cost</td><td class="text-end font-monospace text-danger fw-bold">{{ format_currency($peakHourlyCost) }}/hr</td></tr>
                                    <tr><td>52</td><td>Financial Summary</td><td>Cost Per Unit</td><td class="text-end font-monospace text-dark fw-bolder">{{ format_currency($costPerUnit) }} / unit</td></tr>
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
