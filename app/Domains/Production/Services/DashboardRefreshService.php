<?php

namespace App\Domains\Production\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Domains\Production\Models\ProductionOrderOperation;

class DashboardRefreshService
{
    public function __construct(
        private readonly OeeCalculationService $oeeService,
        private readonly KpiCalculationService $kpiService
    ) {}

    /**
     * Refresh data for the executive dashboard.
     */
    public function refreshExecutiveDashboard(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today() : Carbon::parse($filters['date_start']);
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $filters['date_start'] = $start->toDateTimeString();
        $filters['date_end']   = $end->toDateTimeString();

        $summary      = $this->kpiService->getProductionSummary($tenantId, $filters);
        $utilizations = $this->kpiService->getUtilizations($tenantId, $filters);
        $scrapStats   = $this->kpiService->getScrapAndRejects($tenantId, $filters);

        // Calculate average OEE and aggregate Six Big Losses across machines
        $machinesQuery = DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (!empty($filters['work_center_id'])) {
            $machinesQuery->where('work_center_id', $filters['work_center_id']);
        }

        $machines = $machinesQuery->get();

        $oeeSum = 0.0;
        $machineOees = [];
        $lossesAgg = [
            'equipment_failure_minutes' => 0.0,
            'setup_adjustment_minutes'  => 0.0,
            'minor_stops_minutes'        => 0.0,
            'reduced_speed_minutes'     => 0.0,
            'startup_rejects_count'      => 0.0,
            'production_rejects_count'   => 0.0,
        ];

        $andonCounts = [
            'Running'   => 0,
            'Idle'      => 0,
            'Setup'     => 0,
            'Breakdown' => 0,
        ];

        foreach ($machines as $m) {
            $mOee = $this->oeeService->calculateForMachine($tenantId, $m->id, $start, $end);
            $mLosses = $this->oeeService->calculateSixBigLosses($tenantId, $m->id, $start, $end);

            $oeeSum += $mOee['oee'];
            $machineOees[] = [
                'machine_id' => $m->id,
                'name'       => $m->name,
                'oee'        => $mOee['oee'],
            ];

            $lossesAgg['equipment_failure_minutes'] += $mLosses['equipment_failure_minutes'];
            $lossesAgg['setup_adjustment_minutes']  += $mLosses['setup_adjustment_minutes'];
            $lossesAgg['minor_stops_minutes']       += $mLosses['minor_stops_minutes'];
            $lossesAgg['reduced_speed_minutes']      += $mLosses['reduced_speed_minutes'];
            $lossesAgg['startup_rejects_count']     += $mLosses['startup_rejects_count'];
            $lossesAgg['production_rejects_count']   += $mLosses['production_rejects_count'];

            $stateKey = in_array($m->current_state, ['Running', 'Idle', 'Setup', 'Breakdown']) ? $m->current_state : 'Idle';
            $andonCounts[$stateKey]++;
        }
        $avgOee = $machines->isNotEmpty() ? ($oeeSum / $machines->count()) : 0.00;

        // Calculate actual Downtime Rate (%)
        $totalDowntimeMins = (float) DB::table('production_machine_downtimes')
            ->where('tenant_id', $tenantId)
            ->whereBetween('start_time', [$start, $end])
            ->when(!empty($filters['work_center_id']), function($q) use ($filters) {
                $q->whereIn('machine_id', DB::table('production_machines')->where('work_center_id', $filters['work_center_id'])->pluck('id'));
            })
            ->sum('duration_minutes');

        $totalPlannedMins = max(480.0, $machines->count() * 480.0);
        $downtimeRate = $totalPlannedMins > 0 ? round(($totalDowntimeMins / $totalPlannedMins) * 100, 2) : 0.00;

        return [
            'today_oee'          => $this->kpiService->getKpiWithTargetsAndVariance($tenantId, 'oee', $avgOee),
            'production_summary' => $summary,
            'utilizations'       => $utilizations,
            'scrap_stats'        => $scrapStats,
            'six_big_losses'     => $lossesAgg,
            'andon_counts'       => $andonCounts,
            'machine_oees'       => $machineOees,
            'downtime_rate'      => $downtimeRate,
            'timestamp'          => now()->toIso8601String(),
        ];
    }

    /**
     * Refresh individual machine OEE, run hours, status.
     */
    public function refreshMachineDashboard(int $tenantId, int $machineId): array
    {
        $start = Carbon::today();
        $end   = Carbon::today()->endOfDay();

        $machine = DB::table('production_machines')
            ->where('id', $machineId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if (!$machine) {
            throw new \InvalidArgumentException('Machine not found or access denied.');
        }

        $metrics = $this->oeeService->calculateForMachine($tenantId, $machineId, $start, $end);
        $losses  = $this->oeeService->calculateSixBigLosses($tenantId, $machineId, $start, $end);

        return [
            'machine_id'    => $machineId,
            'name'          => $machine->name ?? 'Unknown',
            'current_state' => $machine->current_state ?? 'Unknown',
            'metrics'       => $metrics,
            'losses'        => $losses,
            'timestamp'     => now()->toIso8601String(),
        ];
    }

    /**
     * Refresh work center load, efficiency, running machines.
     */
    public function refreshWorkCenterDashboard(int $tenantId, int $wcId): array
    {
        $start = Carbon::today();
        $end   = Carbon::today()->endOfDay();

        $wc = DB::table('production_work_centers')
            ->where('id', $wcId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if (!$wc) {
            throw new \InvalidArgumentException('Work Center not found or access denied.');
        }

        $metrics = $this->oeeService->calculateForWorkCenter($tenantId, $wcId, $start, $end);

        $running = DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->where('work_center_id', $wcId)
            ->where('current_state', 'Running')
            ->whereNull('deleted_at')
            ->count();

        $total = DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->where('work_center_id', $wcId)
            ->whereNull('deleted_at')
            ->count();

        return [
            'work_center_id'   => $wcId,
            'metrics'          => $metrics,
            'running_machines' => $running,
            'total_machines'   => $total,
            'timestamp'        => now()->toIso8601String(),
        ];
    }

    /**
     * Refresh Andon status board.
     */
    public function refreshAndonBoard(int $tenantId): array
    {
        $start = Carbon::today();
        $end   = Carbon::today()->endOfDay();

        $machines = DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        // Preload work centers
        $workCenters = DB::table('production_work_centers')
            ->where('tenant_id', $tenantId)
            ->pluck('name', 'id');

        $states = [];
        $runningCount = 0;
        $idleCount = 0;
        $setupCount = 0;
        $breakdownCount = 0;
        $maintenanceCount = 0;
        $offlineCount = 0;
        $totalOeeSum = 0.0;

        foreach ($machines as $m) {
            $mOee = $this->oeeService->calculateForMachine($tenantId, $m->id, $start, $end);
            $totalOeeSum += $mOee['oee'];

            // Fetch active running or latest active operation on this machine
            $activeOp = ProductionOrderOperation::with(['order', 'operator'])
                ->where('tenant_id', $tenantId)
                ->where(function($q) use ($m) {
                    $q->where('machine_used_id', $m->id)
                      ->orWhere('machine_id', $m->id);
                })
                ->whereIn('status', [
                    ProductionOrderOperation::STATUS_RUNNING,
                    ProductionOrderOperation::STATUS_PAUSED,
                    ProductionOrderOperation::STATUS_READY,
                    ProductionOrderOperation::STATUS_COMPLETED
                ])
                ->latest('updated_at')
                ->first();

            $operatorName = '—';
            $activeOrder = '—';
            $activeOpName = '—';
            $qtyProduced = 0;

            if ($activeOp) {
                if ($activeOp->operator) {
                    $operatorName = $activeOp->operator->name;
                }
                if ($activeOp->order) {
                    $activeOrder = $activeOp->order->order_number;
                }
                $activeOpName = $activeOp->name;
                $qtyProduced = (float) $activeOp->quantity_produced;
            }

            $state = $m->current_state ?? 'Offline';
            match(strtolower($state)) {
                'running'         => $runningCount++,
                'idle', 'waiting' => $idleCount++,
                'setup'           => $setupCount++,
                'breakdown'       => $breakdownCount++,
                'maintenance'     => $maintenanceCount++,
                default           => $offlineCount++,
            };

            $states[] = [
                'machine_id'           => $m->id,
                'code'                 => $m->code,
                'name'                 => $m->name,
                'work_center_name'     => $workCenters[$m->work_center_id] ?? '—',
                'current_state'        => $state,
                'current_state_reason' => $m->current_state_reason ?? '—',
                'operator_name'        => $operatorName,
                'active_order'         => $activeOrder,
                'active_op_name'       => $activeOpName,
                'qty_produced'         => $qtyProduced,
                'today_oee'            => round($mOee['oee'], 2),
                'availability'         => round($mOee['availability'], 2),
                'performance'          => round($mOee['performance'], 2),
                'quality'              => round($mOee['quality'], 2),
            ];
        }

        $avgOee = $machines->isNotEmpty() ? round($totalOeeSum / $machines->count(), 2) : 0.00;

        return [
            'total_machines'    => $machines->count(),
            'running_count'     => $runningCount,
            'idle_count'        => $idleCount,
            'setup_count'       => $setupCount,
            'breakdown_count'   => $breakdownCount,
            'maintenance_count' => $maintenanceCount,
            'offline_count'     => $offlineCount,
            'avg_oee'           => $avgOee,
            'machines'          => $states,
            'timestamp'         => now()->toIso8601String(),
        ];
    }

    /**
     * Refresh KPIs datasets.
     */
    public function refreshKpis(int $tenantId, array $filters = []): array
    {
        return [
            'production'   => $this->kpiService->getProductionSummary($tenantId, $filters),
            'utilizations' => $this->kpiService->getUtilizations($tenantId, $filters),
            'cycle_times'  => $this->kpiService->getCycleTimes($tenantId, $filters),
            'quality'      => $this->kpiService->getScrapAndRejects($tenantId, $filters),
            'timestamp'    => now()->toIso8601String(),
        ];
    }
}
