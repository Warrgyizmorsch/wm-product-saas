<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionKpiTarget;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMachineStateHistory;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KpiCalculationService
{
    /**
     * Get target, calculate variance and status for a KPI.
     */
    public function getKpiWithTargetsAndVariance(int $tenantId, string $kpiName, float $currentValue): array
    {
        $targetRecord = ProductionKpiTarget::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('kpi_name', $kpiName)
            ->first();

        // Default targets if not configured
        $defaultTarget = match ($kpiName) {
            'oee'          => 85.00,
            'availability' => 90.00,
            'performance'  => 95.00,
            'quality'      => 99.00,
            'throughput'   => 100.00,
            'utilization'  => 80.00,
            'scrap_rate'   => 2.00,
            'downtime'     => 10.00,
            default        => 0.00,
        };

        $targetValue = $targetRecord ? (float) $targetRecord->target_value : $defaultTarget;
        $variance = $currentValue - $targetValue;

        // Determine if above or below target based on KPI context
        // For scrap and downtime, lower is better. For others, higher is better.
        $lowerIsBetter = in_array($kpiName, ['scrap_rate', 'downtime']);
        
        if ($variance === 0.0) {
            $status = 'On Target';
        } elseif ($lowerIsBetter) {
            $status = $variance < 0 ? 'Above Target' : 'Below Target'; // Less scrap = above target
        } else {
            $status = $variance > 0 ? 'Above Target' : 'Below Target';
        }

        return [
            'kpi_name'      => $kpiName,
            'current_value' => round($currentValue, 2),
            'target_value'  => round($targetValue, 2),
            'variance'      => round($variance, 2),
            'status'        => $status,
        ];
    }

    /**
     * Get order status and count stats.
     */
    public function getProductionSummary(int $tenantId, array $filters = []): array
    {
        $query = ProductionOrder::where('tenant_id', $tenantId);

        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $query->whereBetween('created_at', [$filters['date_start'], $filters['date_end']]);
        }

        $orders = $query->get();

        $created   = $orders->count();
        $released  = $orders->where('status', ProductionOrder::STATUS_RELEASED)->count();
        $completed = $orders->where('status', ProductionOrder::STATUS_COMPLETED)->count();
        $delayed   = $orders->where('status', ProductionOrder::STATUS_IN_PROGRESS)->filter(fn($o) => $o->end_date && $o->end_date->isPast())->count();
        $cancelled = $orders->where('status', ProductionOrder::STATUS_CANCELLED)->count();

        // Planned vs Actual
        $plannedQty = (float) $orders->sum('quantity_ordered');
        $actualQty  = (float) $orders->sum('quantity_produced');

        // Schedule adherence
        $scheduleAdherence = 100.00;
        $totalSched = ProductionScheduleOperation::where('tenant_id', $tenantId)->count();
        if ($totalSched > 0) {
            $onTimeSched = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->whereColumn('actual_finish', '<=', 'planned_finish')
                ->count();
            $scheduleAdherence = ($onTimeSched / $totalSched) * 100;
        }

        return [
            'orders_created'     => $created,
            'orders_released'    => $released,
            'orders_completed'   => $completed,
            'orders_delayed'     => $delayed,
            'orders_cancelled'   => $cancelled,
            'planned_quantity'   => $plannedQty,
            'actual_quantity'    => $actualQty,
            'schedule_adherence' => round($scheduleAdherence, 2),
        ];
    }

    /**
     * Get asset utilizations (machines, operators, work centers).
     */
    /**
     * Get asset utilizations (machines, operators, work centers).
     */
    public function getUtilizations(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? \Carbon\Carbon::today() : \Carbon\Carbon::parse($filters['date_start']);
        $end   = empty($filters['date_end']) ? \Carbon\Carbon::today()->endOfDay() : \Carbon\Carbon::parse($filters['date_end']);

        // Machine Utilization: machines currently Running OR machines with logged run time in date range
        $totalMachines = Machine::where('tenant_id', $tenantId)->count();
        $utilizedMachineIds = DB::table('production_order_progress_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('recorded_at', [$start, $end])
            ->pluck('machine_id')
            ->merge(Machine::where('tenant_id', $tenantId)->where('current_state', 'Running')->pluck('id'))
            ->filter()
            ->unique();

        $machineUtilization = $totalMachines > 0 ? ($utilizedMachineIds->count() / $totalMachines) * 100 : 0.00;

        // Operator Utilization
        $totalOperators = User::where('tenant_id', $tenantId)->count();
        $activeAssignments = DB::table('production_operator_assignments')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['accepted', 'completed'])
            ->whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        if ($activeAssignments === 0) {
            $activeAssignments = DB::table('production_operator_assignments')
                ->where('tenant_id', $tenantId)
                ->where('status', 'accepted')
                ->count();
        }

        $operatorUtilization = $totalOperators > 0 ? min(100.00, ($activeAssignments / $totalOperators) * 100) : 0.00;

        // Work Center Utilization: work centers with active, running, or completed operations in date range
        $totalWcs = WorkCenter::where('tenant_id', $tenantId)->count();
        $utilizedWcIds = DB::table('production_order_operations')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [ProductionOrderOperation::STATUS_RUNNING, ProductionOrderOperation::STATUS_COMPLETED])
            ->whereBetween('updated_at', [$start, $end])
            ->pluck('work_center_id')
            ->merge(
                ProductionScheduleOperation::where('tenant_id', $tenantId)
                    ->whereIn('status', [ProductionScheduleOperation::STATUS_RUNNING, ProductionScheduleOperation::STATUS_COMPLETED])
                    ->pluck('work_center_id')
            )
            ->filter()
            ->unique();

        $wcUtilization = $totalWcs > 0 ? ($utilizedWcIds->count() / $totalWcs) * 100 : 0.00;

        return [
            'machine_utilization'     => round($machineUtilization, 2),
            'operator_utilization'    => round($operatorUtilization, 2),
            'work_center_utilization' => round($wcUtilization, 2),
        ];
    }

    /**
     * Get cycle, setup, waiting, and lead times.
     */
    public function getCycleTimes(int $tenantId, array $filters = []): array
    {
        $opsQuery = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('status', ProductionOrderOperation::STATUS_COMPLETED);

        $avgSetup = (float) $opsQuery->avg('setup_time_actual');
        $avgProcess = (float) $opsQuery->avg('processing_time_actual');
        $avgCycle = $avgSetup + $avgProcess;

        // Average Waiting Time
        $schedules = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereNotNull('actual_start')
            ->get();

        $waitSum = 0;
        $waitCount = 0;
        foreach ($schedules as $s) {
            if ($s->actual_start && $s->planned_start) {
                $waitSum += max(0, $s->actual_start->diffInMinutes($s->planned_start));
                $waitCount++;
            }
        }
        $avgWait = $waitCount > 0 ? ($waitSum / $waitCount) : 0.00;

        return [
            'avg_setup_time'      => round($avgSetup, 2),
            'avg_processing_time' => round($avgProcess, 2),
            'avg_cycle_time'      => round($avgCycle, 2),
            'avg_waiting_time'    => round($avgWait, 2),
        ];
    }

    /**
     * Get quality, yield, and rejects statistics.
     */
    public function getScrapAndRejects(int $tenantId, array $filters = []): array
    {
        $logs = ProductionOrderProgressLog::where('tenant_id', $tenantId);

        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $logs->whereBetween('recorded_at', [$filters['date_start'], $filters['date_end']]);
        }

        $allLogs = $logs->get();

        $produced = (float) $allLogs->sum('quantity_produced');
        $rejected = (float) $allLogs->sum('quantity_rejected');
        $scrapped = (float) $allLogs->sum('quantity_scrapped');
        $totalInput = $produced + $rejected + $scrapped;

        $scrapRate  = $totalInput > 0 ? ($scrapped / $totalInput) * 100 : 0.00;
        $rejectRate = $totalInput > 0 ? ($rejected / $totalInput) * 100 : 0.00;
        $yield      = $totalInput > 0 ? ($produced / $totalInput) * 100 : 100.00;

        return [
            'scrap_rate'  => round($scrapRate, 2),
            'reject_rate' => round($rejectRate, 2),
            'yield'       => round($yield, 2),
            'produced'    => $produced,
        ];
    }
}
