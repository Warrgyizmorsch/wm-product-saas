<?php

namespace App\Domains\Production\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        $summary      = $this->kpiService->getProductionSummary($tenantId, $filters);
        $utilizations = $this->kpiService->getUtilizations($tenantId, $filters);
        $scrapStats   = $this->kpiService->getScrapAndRejects($tenantId, $filters);

        // Calculate average OEE across all machines
        $machines = DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        $oeeSum = 0.0;
        $machineOees = [];
        foreach ($machines as $m) {
            $mOee = $this->oeeService->calculateForMachine($tenantId, $m->id, $start, $end);
            $oeeSum += $mOee['oee'];
            $machineOees[] = [
                'machine_id' => $m->id,
                'name'       => $m->name,
                'oee'        => $mOee['oee'],
            ];
        }
        $avgOee = $machines->isNotEmpty() ? ($oeeSum / $machines->count()) : 0.00;

        return [
            'today_oee'          => $this->kpiService->getKpiWithTargetsAndVariance($tenantId, 'oee', $avgOee),
            'production_summary' => $summary,
            'utilizations'       => $utilizations,
            'scrap_stats'        => $scrapStats,
            'machine_oees'       => $machineOees,
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
            ->where('work_center_id', $wcId)
            ->where('current_state', 'Running')
            ->count();

        $total = DB::table('production_machines')
            ->where('work_center_id', $wcId)
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
        $machines = DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        $states = [];
        foreach ($machines as $m) {
            $states[] = [
                'machine_id'    => $m->id,
                'name'          => $m->name,
                'current_state' => $m->current_state,
                'reason'        => $m->current_state_reason,
            ];
        }

        return [
            'machines'  => $states,
            'timestamp' => now()->toIso8601String(),
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
