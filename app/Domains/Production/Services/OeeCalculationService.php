<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMachineStateHistory;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionScheduleOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OeeCalculationService
{
    /**
     * Calculate OEE for a machine.
     */
    public function calculateForMachine(int $tenantId, int $machineId, Carbon $start, Carbon $end): array
    {
        // 1. Planned Run Time (minutes)
        // Check schedule operations duration or default to date range diff
        $plannedRunTime = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->whereBetween('planned_start', [$start, $end])
            ->sum('planned_duration_minutes');

        if ($plannedRunTime <= 0) {
            $plannedRunTime = max(480, (float) $start->diffInMinutes($end));
        }

        // 2. Downtime Minutes
        $downtimeMinutes = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->whereBetween('start_time', [$start, $end])
            ->sum('duration_minutes');

        $runTime = max(0.00, $plannedRunTime - $downtimeMinutes);

        // 3. Quantities
        $logs = ProductionOrderProgressLog::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->whereBetween('recorded_at', [$start, $end])
            ->get();

        $totalProduced = (float) $logs->sum('quantity_produced');
        $rejected      = (float) $logs->sum('quantity_rejected');
        $scrapped      = (float) $logs->sum('quantity_scrapped');
        $goodQty       = max(0.00, $totalProduced - $rejected - $scrapped);

        // 4. Standard Cycle Time
        // Find average planned processing time from routing operations
        $avgProcessingTime = (float) ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->avg('processing_time_planned');

        $standardCycleTime = $avgProcessingTime > 0 ? $avgProcessingTime : 2.5; // Default 2.5 minutes per unit if empty

        // 5. OEE Components
        $availability = $plannedRunTime > 0 ? ($runTime / $plannedRunTime) : 1.0;
        
        $expectedProductionTime = $totalProduced * $standardCycleTime;
        $performance = $runTime > 0 ? min(1.0, $expectedProductionTime / $runTime) : 1.0;
        
        $quality = $totalProduced > 0 ? ($goodQty / $totalProduced) : 1.0;

        $oee = $availability * $performance * $quality * 100;

        return [
            'planned_run_time_minutes' => $plannedRunTime,
            'run_time_minutes'         => $runTime,
            'downtime_minutes'         => $downtimeMinutes,
            'total_produced'           => $totalProduced,
            'good_quantity'            => $goodQty,
            'rejected_quantity'        => $rejected,
            'scrapped_quantity'        => $scrapped,
            'availability'             => round($availability * 100, 2),
            'performance'              => round($performance * 100, 2),
            'quality'                  => round($quality * 100, 2),
            'oee'                      => round($oee, 2),
        ];
    }

    /**
     * Calculate Six Big Losses.
     */
    public function calculateSixBigLosses(int $tenantId, int $machineId, Carbon $start, Carbon $end): array
    {
        $oeeMetrics = $this->calculateForMachine($tenantId, $machineId, $start, $end);
        $standardCycleTime = 2.5;

        // 1. Equipment Failure (Breakdowns)
        $equipmentFailure = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->whereIn('category', ['Breakdown', 'Corrective Maintenance'])
            ->whereBetween('start_time', [$start, $end])
            ->sum('duration_minutes');

        // 2. Setup & Adjustment
        $setupAdjustment = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->whereIn('category', ['Setup', 'Tool Change', 'Calibration'])
            ->whereBetween('start_time', [$start, $end])
            ->sum('duration_minutes');

        // 3. Minor Stops
        $minorStops = (float) ProductionMachineStateHistory::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->where('state', 'Idle')
            ->where('duration_seconds', '<', 300) // less than 5 minutes
            ->whereBetween('started_at', [$start, $end])
            ->sum('duration_seconds') / 60.0;

        // 4. Reduced Speed
        $netRunTime = $oeeMetrics['run_time_minutes'];
        $actualProcessTime = $oeeMetrics['total_produced'] * $standardCycleTime;
        $reducedSpeed = max(0.00, $netRunTime - $actualProcessTime - $minorStops);

        // 5. Startup Rejects
        // Map startup rejects as scrap logged on first sequences (e.g. sequence = 10)
        $startupRejects = (float) ProductionOrderProgressLog::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->whereHas('operation', fn($q) => $q->where('sequence', '<=', 10))
            ->whereBetween('recorded_at', [$start, $end])
            ->sum('quantity_scrapped');

        // 6. Production Rejects
        $totalScrap = $oeeMetrics['scrapped_quantity'] + $oeeMetrics['rejected_quantity'];
        $productionRejects = max(0.00, $totalScrap - $startupRejects);

        return [
            'equipment_failure_minutes' => round($equipmentFailure, 2),
            'setup_adjustment_minutes'  => round($setupAdjustment, 2),
            'minor_stops_minutes'       => round($minorStops, 2),
            'reduced_speed_minutes'      => round($reducedSpeed, 2),
            'startup_rejects_count'     => round($startupRejects, 2),
            'production_rejects_count'    => round($productionRejects, 2),
        ];
    }

    /**
     * Calculate OEE for a work center.
     */
    public function calculateForWorkCenter(int $tenantId, int $wcId, Carbon $start, Carbon $end): array
    {
        $machines = DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->where('work_center_id', $wcId)
            ->whereNull('deleted_at')
            ->get();

        if ($machines->isEmpty()) {
            return [
                'availability' => 0.00,
                'performance'  => 0.00,
                'quality'      => 0.00,
                'oee'          => 0.00,
            ];
        }

        $availSum = 0.0;
        $perfSum  = 0.0;
        $qualSum  = 0.0;
        $oeeSum   = 0.0;

        foreach ($machines as $machine) {
            $metrics = $this->calculateForMachine($tenantId, $machine->id, $start, $end);
            $availSum += $metrics['availability'];
            $perfSum  += $metrics['performance'];
            $qualSum  += $metrics['quality'];
            $oeeSum   += $metrics['oee'];
        }

        $count = $machines->count();

        return [
            'availability' => round($availSum / $count, 2),
            'performance'  => round($perfSum / $count, 2),
            'quality'      => round($qualSum / $count, 2),
            'oee'          => round($oeeSum / $count, 2),
        ];
    }

    /**
     * Calculate OEE for a production order.
     */
    public function calculateForOrder(int $tenantId, int $orderId): array
    {
        $start = Carbon::now()->subMonths(6);
        $end   = Carbon::now();

        // 1. Planned Run Time (minutes)
        $plannedRunTime = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->sum('planned_duration_minutes');

        if ($plannedRunTime <= 0) {
            $plannedRunTime = 480.00;
        }

        // 2. Downtime Minutes
        $downtimeMinutes = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->sum('duration_minutes');

        $runTime = max(0.00, $plannedRunTime - $downtimeMinutes);

        // 3. Quantities
        $logs = ProductionOrderProgressLog::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->get();

        $totalProduced = (float) $logs->sum('quantity_produced');
        $rejected      = (float) $logs->sum('quantity_rejected');
        $scrapped      = (float) $logs->sum('quantity_scrapped');
        $goodQty       = max(0.00, $totalProduced - $rejected - $scrapped);

        // 4. Standard Cycle Time
        $avgProcessingTime = (float) ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->avg('processing_time_planned');

        $standardCycleTime = $avgProcessingTime > 0 ? $avgProcessingTime : 2.5;

        // 5. OEE Components
        $availability = $plannedRunTime > 0 ? ($runTime / $plannedRunTime) : 1.0;
        $expectedProductionTime = $totalProduced * $standardCycleTime;
        $performance = $runTime > 0 ? min(1.0, $expectedProductionTime / $runTime) : 1.0;
        $quality = $totalProduced > 0 ? ($goodQty / $totalProduced) : 1.0;

        $oee = $availability * $performance * $quality * 100;

        return [
            'availability' => round($availability * 100, 2),
            'performance'  => round($performance * 100, 2),
            'quality'      => round($quality * 100, 2),
            'oee'          => round($oee, 2),
        ];
    }
}
