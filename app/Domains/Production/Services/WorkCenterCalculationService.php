<?php

namespace App\Domains\Production\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterCalculation;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkCenterCalculationService
{
    public function __construct(
        private readonly ?SectionCalculationService $sectionCalculationService = null,
        private readonly ?WorkCenterActivityLogger $activityLogger = null,
    ) {}

    protected function getSectionCalculationService(): SectionCalculationService
    {
        return $this->sectionCalculationService ?? app(SectionCalculationService::class);
    }

    protected function getActivityLogger(): WorkCenterActivityLogger
    {
        return $this->activityLogger ?? app(WorkCenterActivityLogger::class);
    }

    /**
     * Compute full calculation payload for a Work Center roll-up.
     */
    public function calculate(WorkCenter $workCenter, ?array $sectionCalculationResults = null): array
    {
        $tenantId = (int) $workCenter->tenant_id;

        // 1. Resolve Active Sections
        $sections = $workCenter->relationLoaded('sections')
            ? $workCenter->sections->where('tenant_id', $tenantId)->filter(fn($sec) => $sec->isActive())
            : $workCenter->sections()->where('tenant_id', $tenantId)->active()->with(['machines', 'manualProductions'])->get();

        $sectionCalcService = $this->getSectionCalculationService();

        // 2. Obtain Section Calculations
        $sectionDetails = [];
        $totalSectionDailyCap    = 0.0;
        $totalSectionHourlyCap   = 0.0;
        $totalSectionDailyLabor  = 0.0;
        $totalSectionDailyMach   = 0.0;
        $totalSectionHourlyMach  = 0.0;

        $allShiftsAcrossSections = [];

        foreach ($sections as $section) {
            // Use passed in calculation results or calculate on the fly
            $secCalc = $sectionCalculationResults[$section->id] ?? $sectionCalcService->calculate($section);

            $totalSectionDailyCap   += (float) ($secCalc['daily_capacity'] ?? 0.0);
            $totalSectionHourlyCap  += (float) ($secCalc['capacity_per_hour'] ?? 0.0);
            $totalSectionDailyLabor += (float) ($secCalc['total_daily_labor_cost'] ?? 0.0);
            $totalSectionDailyMach  += (float) ($secCalc['total_daily_machine_cost'] ?? 0.0);
            $totalSectionHourlyMach += (float) ($secCalc['machine_cost_per_hour'] ?? 0.0);

            $secShifts = $secCalc['calculation_details']['operating_timeline']['shifts'] 
                ?? $secCalc['calculation_details']['operating_hours']['shifts'] 
                ?? [];
            foreach ($secShifts as $s) {
                $allShiftsAcrossSections[$s['id']] = $s;
            }

            $sectionDetails[] = [
                'section_id'                 => $section->id,
                'section_code'               => $section->code,
                'section_name'               => $section->name,
                'capacity_per_hour'          => (float) ($secCalc['capacity_per_hour'] ?? 0.0),
                'daily_capacity'             => (float) ($secCalc['daily_capacity'] ?? 0.0),
                'labor_cost_per_hour'        => (float) ($secCalc['labor_cost_per_hour'] ?? 0.0),
                'total_daily_labor_cost'     => (float) ($secCalc['total_daily_labor_cost'] ?? 0.0),
                'machine_cost_per_hour'      => (float) ($secCalc['machine_cost_per_hour'] ?? 0.0),
                'total_daily_machine_cost'   => (float) ($secCalc['total_daily_machine_cost'] ?? 0.0),
                'total_daily_operating_cost' => (float) ($secCalc['total_daily_operating_cost'] ?? 0.0),
                'unique_operating_hours'     => (float) ($secCalc['unique_operating_hours'] ?? 0.0),
            ];
        }

        // 3. Work Center Unique Operating Hours (Union of clock intervals across all sections)
        $operatingHoursData = $this->calculateUniqueOperatingHours(array_values($allShiftsAcrossSections));
        $uniqueOperatingHours = (float) ($operatingHoursData['unique_clock_hours'] ?? 0.0);

        // 4. Direct Work Center Staff (section_id IS NULL)
        $directStaff = $this->calculateDirectStaffLabor($workCenter);
        $directStaffDailyLabor = (float) ($directStaff['total_daily_cost'] ?? 0.0);

        // 5. Work Center Aggregated Labor
        $wcDailyLaborCost = round($totalSectionDailyLabor + $directStaffDailyLabor, 4);
        $wcLaborCostPerHour = $uniqueOperatingHours > 0
            ? round($wcDailyLaborCost / $uniqueOperatingHours, 4)
            : 0.0;

        // 6. Work Center Machine Expenses
        $wcDailyMachineCost = round($totalSectionDailyMach, 4);
        $wcHourlyMachineCost = $uniqueOperatingHours > 0
            ? round($wcDailyMachineCost / $uniqueOperatingHours, 4)
            : 0.0;

        // 7. Overhead & Total Operating Cost
        $overheadDailyCost = round((float) ($workCenter->overhead_cost ?? 0.0), 4);

        $wcTotalDailyCost = round($wcDailyLaborCost + $wcDailyMachineCost + $overheadDailyCost, 4);
        $wcTotalCostPerHour = $uniqueOperatingHours > 0
            ? round($wcTotalDailyCost / $uniqueOperatingHours, 4)
            : 0.0;

        // Capacity: Total Daily & True Average Hourly (Daily / Unique Operating Hours)
        $wcDailyCapacity = round($totalSectionDailyCap, 4);
        $wcHourlyCapacity = $uniqueOperatingHours > 0
            ? round($wcDailyCapacity / $uniqueOperatingHours, 4)
            : 0.0;

        // Financial Unit Cost
        $wcCostPerUnit = $wcDailyCapacity > 0
            ? round($wcTotalDailyCost / $wcDailyCapacity, 4)
            : 0.0;

        // 8. Detailed Calculation JSON
        $calculationDetails = [
            'operating_hours' => [
                'unique_clock_hours'   => $uniqueOperatingHours,
                'unique_clock_minutes' => $operatingHoursData['unique_clock_minutes'] ?? 0,
                'operational_window'   => $operatingHoursData['operational_window'] ?? 'None',
                'shifts_count'         => count($allShiftsAcrossSections),
            ],
            'sections_contribution' => $sectionDetails,
            'sections'              => $sectionDetails, // alias for modal compatibility
            'direct_work_center_staff' => [
                'employees'        => $directStaff['employees'],
                'total_daily_cost' => $directStaffDailyLabor,
            ],
            'direct_personnel'      => [ // alias for modal compatibility
                'employees'        => $directStaff['employees'],
                'total_daily_cost' => $directStaffDailyLabor,
            ],
            'overhead' => [
                'flat_daily_amount'      => $overheadDailyCost,
                'note'                   => 'Direct flat daily operating expense entered in Work Center form',
            ],
            'totals' => [
                'total_daily_capacity'         => $wcDailyCapacity,
                'total_hourly_capacity'        => $wcHourlyCapacity,
                'section_daily_labor_cost'     => round($totalSectionDailyLabor, 4),
                'direct_staff_daily_labor_cost'=> $directStaffDailyLabor,
                'total_daily_labor_cost'       => $wcDailyLaborCost,
                'average_hourly_labor_cost'    => $wcLaborCostPerHour,
                'total_daily_machine_cost'     => $wcDailyMachineCost,
                'total_hourly_machine_cost'    => $wcHourlyMachineCost,
                'overhead_daily_cost'          => $overheadDailyCost,
                'total_daily_cost'             => $wcTotalDailyCost,
                'total_cost_per_hour'          => $wcTotalCostPerHour,
                'cost_per_unit'                => $wcCostPerUnit,
                'unique_operating_hours'       => $uniqueOperatingHours,
            ],
        ];

        return [
            'capacity_per_hour'             => $wcHourlyCapacity,
            'daily_capacity'                => $wcDailyCapacity,
            'labor_cost_per_hour'           => $wcLaborCostPerHour,
            'total_daily_labor_cost'        => $wcDailyLaborCost,
            'direct_staff_daily_labor_cost' => $directStaffDailyLabor,
            'machine_cost_per_hour'         => $wcHourlyMachineCost,
            'total_daily_machine_cost'      => $wcDailyMachineCost,
            'overhead_cost'                 => $overheadDailyCost,
            'total_daily_cost'              => $wcTotalDailyCost,
            'total_cost_per_hour'           => $wcTotalCostPerHour,
            'cost_per_unit'                 => $wcCostPerUnit,
            'unique_operating_hours'        => $uniqueOperatingHours,
            'calculation_details'           => $calculationDetails,
            'calculated_at'                 => now()->toIso8601String(),

            // Legacy backward-compatibility sub-keys
            'capacity' => [
                'capacity_per_hour'          => $wcHourlyCapacity,
                'total_theoretical_capacity' => $wcDailyCapacity,
                'unique_operating_hours'     => $uniqueOperatingHours,
                'capacity_unit'              => 'units/hr',
                'sections'                   => $sectionDetails,
            ],
            'labor_cost' => [
                'labor_cost_per_hour'        => $wcLaborCostPerHour,
                'total_scheduled_labor_cost' => $wcDailyLaborCost,
                'unique_operating_hours'     => $uniqueOperatingHours,
            ],
        ];
    }

    /**
     * Calculate and persist Work Center Rollup Snapshot into production_work_center_calculations.
     * Recalculates impacted sections or all active sections if none specified.
     */
    public function calculateAndSave(
        WorkCenter $workCenter,
        string $triggerType = 'manual',
        ?string $batchId = null,
        array $impactedSectionIds = []
    ): WorkCenterCalculation {
        $batchId = $batchId ?: (string) Str::uuid();
        $tenantId = (int) $workCenter->tenant_id;
        $sectionCalcService = $this->getSectionCalculationService();

        // 1. Resolve sections that need calculation
        $allSections = $workCenter->sections()->where('tenant_id', $tenantId)->active()->get();

        $sectionsToRecalculate = empty($impactedSectionIds)
            ? $allSections
            : $allSections->whereIn('id', $impactedSectionIds);

        $sectionCalculationSnapshots = [];
        $sectionCalculationResults   = [];

        foreach ($allSections as $sec) {
            $isImpacted = $sectionsToRecalculate->contains('id', $sec->id);

            if ($isImpacted) {
                // Calculate and save fresh snapshot for this section in the same batch
                $savedSecCalc = $sectionCalcService->calculateAndSave(
                    section: $sec,
                    triggerType: $triggerType,
                    batchId: $batchId
                );
                $sectionCalculationSnapshots[$sec->id] = $savedSecCalc;
                $sectionCalculationResults[$sec->id]   = [
                    'capacity_per_hour'          => $savedSecCalc->capacity_per_hour,
                    'daily_capacity'             => $savedSecCalc->daily_capacity,
                    'labor_cost_per_hour'        => $savedSecCalc->labor_cost_per_hour,
                    'total_daily_labor_cost'     => $savedSecCalc->total_daily_labor_cost,
                    'machine_cost_per_hour'      => $savedSecCalc->machine_cost_per_hour,
                    'total_daily_machine_cost'   => $savedSecCalc->total_daily_machine_cost,
                    'total_daily_operating_cost' => $savedSecCalc->total_daily_operating_cost,
                    'unique_operating_hours'     => $savedSecCalc->unique_operating_hours,
                    'calculation_details'        => $savedSecCalc->calculation_details,
                ];
            } else {
                // Fetch latest existing section calculation snapshot
                $latestSecCalc = WorkCenterSectionCalculation::where('tenant_id', $tenantId)
                    ->where('section_id', $sec->id)
                    ->latest('id')
                    ->first();

                if ($latestSecCalc) {
                    $sectionCalculationSnapshots[$sec->id] = $latestSecCalc;
                    $sectionCalculationResults[$sec->id]   = [
                        'capacity_per_hour'          => $latestSecCalc->capacity_per_hour,
                        'daily_capacity'             => $latestSecCalc->daily_capacity,
                        'labor_cost_per_hour'        => $latestSecCalc->labor_cost_per_hour,
                        'total_daily_labor_cost'     => $latestSecCalc->total_daily_labor_cost,
                        'machine_cost_per_hour'      => $latestSecCalc->machine_cost_per_hour,
                        'total_daily_machine_cost'   => $latestSecCalc->total_daily_machine_cost,
                        'total_daily_operating_cost' => $latestSecCalc->total_daily_operating_cost,
                        'unique_operating_hours'     => $latestSecCalc->unique_operating_hours,
                        'calculation_details'        => $latestSecCalc->calculation_details,
                    ];
                } else {
                    $freshSecCalc = $sectionCalcService->calculateAndSave(
                        section: $sec,
                        triggerType: $triggerType,
                        batchId: $batchId
                    );
                    $sectionCalculationSnapshots[$sec->id] = $freshSecCalc;
                    $sectionCalculationResults[$sec->id]   = $freshSecCalc->toArray();
                }
            }
        }

        // 2. Roll up Work Center Metrics
        $wcCalc = $this->calculate($workCenter, $sectionCalculationResults);

        // 3. Persist Work Center Snapshot
        $record = WorkCenterCalculation::create([
            'tenant_id'                     => $tenantId,
            'work_center_id'                => (int) $workCenter->id,
            'batch_id'                      => $batchId,
            'trigger_type'                  => $triggerType,
            'impacted_section_ids'          => empty($impactedSectionIds) ? $allSections->pluck('id')->all() : array_values($impactedSectionIds),
            'capacity_per_hour'             => $wcCalc['capacity_per_hour'],
            'daily_capacity'                => $wcCalc['daily_capacity'],
            'labor_cost_per_hour'           => $wcCalc['labor_cost_per_hour'],
            'total_daily_labor_cost'        => $wcCalc['total_daily_labor_cost'],
            'direct_staff_daily_labor_cost' => $wcCalc['direct_staff_daily_labor_cost'],
            'machine_cost_per_hour'         => $wcCalc['machine_cost_per_hour'],
            'total_daily_machine_cost'      => $wcCalc['total_daily_machine_cost'],
            'overhead_cost'                 => $wcCalc['overhead_cost'],
            'total_daily_cost'              => $wcCalc['total_daily_cost'],
            'total_cost_per_hour'           => $wcCalc['total_cost_per_hour'],
            'cost_per_unit'                 => $wcCalc['cost_per_unit'],
            'unique_operating_hours'        => $wcCalc['unique_operating_hours'],
            'calculation_details'           => $wcCalc['calculation_details'],
        ]);

        // 4. Link section calculation snapshots to this parent WC calculation record
        foreach ($sectionCalculationSnapshots as $secSnapshot) {
            if ($secSnapshot->batch_id === $batchId) {
                $secSnapshot->update(['work_center_calculation_id' => $record->id]);
            }
        }

        // 5. Record activity log
        $this->getActivityLogger()->logWorkCenterCalculationCompleted($record);

        return $record;
    }

    /**
     * Calculate direct Work Center employees (work_center_id set, section_id IS NULL).
     * e.g., floating supervisors, maintenance crew, department heads.
     * Incorporates multi-Work Center split allocation and shift-based hourly rates.
     */
    public function calculateDirectStaffLabor(WorkCenter $workCenter): array
    {
        $tenantId = (int) $workCenter->tenant_id;

        $assignments = WorkCenterEmployeeAssignment::where('tenant_id', $tenantId)
            ->where('work_center_id', $workCenter->id)
            ->whereNull('section_id')
            ->where('active', true)
            ->with(['employee.shift', 'employee.defaultShift'])
            ->get();

        $employees = [];
        $totalDailyCost = 0.0;

        foreach ($assignments as $assignment) {
            $emp = $assignment->employee;
            if (!$emp || !(bool) ($emp->status ?? true)) {
                continue;
            }

            // 1. Count active direct Work Center assignments for this employee
            $activeWcCount = WorkCenterEmployeeAssignment::where('tenant_id', $tenantId)
                ->where('employee_id', $emp->id)
                ->whereNull('section_id')
                ->where('active', true)
                ->distinct('work_center_id')
                ->count('work_center_id');
            $activeWcCount = max(1, (int) $activeWcCount);

            // 2. Base Daily Salary & Allocated Daily Cost for this Work Center
            $monthlySalary        = (float) ($emp->current_salary ?? 0.0);
            $baseDailySalary      = round($monthlySalary / 30, 4);
            $allocatedDailySalary = round($baseDailySalary / $activeWcCount, 4);

            // 3. Resolve Assigned Shift & Hourly Rate
            $empShift = $emp->defaultShift ?? $emp->shift ?? (method_exists($emp, 'resolveShiftForDate') ? $emp->resolveShiftForDate(now()) : null);
            $shiftDurationHours = $empShift ? (float) ($empShift->working_hours ?? 8.0) : 8.0;
            if ($shiftDurationHours <= 0) {
                $shiftDurationHours = 8.0;
            }

            $hourlyRate = round($allocatedDailySalary / $shiftDurationHours, 4);
            $shiftName  = $empShift?->name ?? 'Standard Shift';

            $formula = "Monthly CTC ($" . number_format($monthlySalary, 2) . ") / 30 days = $" . number_format($baseDailySalary, 2) . "/day / {$activeWcCount} active WCs = $" . number_format($allocatedDailySalary, 2) . "/day; / " . format_hours($shiftDurationHours) . " shift hrs = $" . number_format($hourlyRate, 2) . "/hr";

            $employees[] = [
                'assignment_id'           => $assignment->id,
                'employee_id'             => $emp->id,
                'employee_code'           => $emp->employee_id ?? "EMP-{$emp->id}",
                'name'                    => $emp->full_name,
                'role'                    => $emp->job_title ?: 'Work Center Staff',
                'hrms_net_monthly_salary' => $monthlySalary,
                'monthly_ctc'             => $monthlySalary,
                'base_daily_salary'       => $baseDailySalary,
                'active_wc_count'         => $activeWcCount,
                'daily_salary'            => $allocatedDailySalary,
                'daily_cost'              => $allocatedDailySalary,
                'shift_name'              => $shiftName,
                'shift_duration_hours'    => $shiftDurationHours,
                'hourly_rate'             => $hourlyRate,
                'formula'                 => $formula,
            ];

            $totalDailyCost += $allocatedDailySalary;
        }

        return [
            'employees'        => $employees,
            'total_daily_cost' => round($totalDailyCost, 4),
        ];
    }

    /**
     * Calculate union of unique clock hours across an array of shift configurations.
     */
    protected function calculateUniqueOperatingHours(array $shiftsData): array
    {
        if (empty($shiftsData)) {
            return [
                'unique_clock_hours'   => 0.0,
                'unique_clock_minutes' => 0,
                'operational_window'   => 'None',
            ];
        }

        $covered = array_fill(0, 1440, false);

        foreach ($shiftsData as $s) {
            $sMin   = (int) ($s['start_minute'] ?? 0);
            $eMin   = (int) ($s['end_minute'] ?? 0);
            $isOver = (bool) ($s['is_overnight'] ?? false);

            for ($m = 0; $m < 1440; $m++) {
                $isActive = $isOver ? ($m >= $sMin || $m < $eMin) : ($m >= $sMin && $m < $eMin);
                if ($isActive) {
                    $covered[$m] = true;
                }
            }
        }

        $totalCoveredMinutes = count(array_filter($covered));
        $uniqueHours = round($totalCoveredMinutes / 60, 4);

        $window = "{$uniqueHours} hrs operational window across sections";

        return [
            'unique_clock_hours'   => $uniqueHours,
            'unique_clock_minutes' => $totalCoveredMinutes,
            'operational_window'   => $window,
        ];
    }

    /**
     * Legacy helper to calculate capacity alone.
     */
    public function calculateCapacity(WorkCenter $workCenter): array
    {
        $calc = $this->calculate($workCenter);
        return $calc['capacity'];
    }

    /**
     * Legacy helper to calculate labor cost alone.
     */
    public function calculateLaborCost(WorkCenter $workCenter): array
    {
        $calc = $this->calculate($workCenter);
        return $calc['labor_cost'];
    }
}
