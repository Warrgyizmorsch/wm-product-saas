<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\WorkCenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public function __construct(
        private readonly OeeCalculationService $oeeService,
        private readonly KpiCalculationService $kpiService
    ) {}

    /**
     * Generate OEE and usage stats for all machines.
     */
    public function generateMachineReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonth() : Carbon::parse($filters['date_start']);
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $machines = Machine::where('tenant_id', $tenantId)->get();
        $reportData = [];

        foreach ($machines as $m) {
            $metrics = $this->oeeService->calculateForMachine($tenantId, $m->id, $start, $end);
            $losses  = $this->oeeService->calculateSixBigLosses($tenantId, $m->id, $start, $end);

            $reportData[] = [
                'machine_id'        => $m->id,
                'name'              => $m->name,
                'code'              => $m->code,
                'oee'               => $metrics['oee'],
                'availability'      => $metrics['availability'],
                'performance'       => $metrics['performance'],
                'quality'           => $metrics['quality'],
                'total_produced'    => $metrics['total_produced'],
                'good_quantity'     => $metrics['good_quantity'],
                'downtime_minutes'  => $metrics['downtime_minutes'],
                'losses'            => $losses,
            ];
        }

        return [
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'data'         => $reportData,
        ];
    }

    /**
     * Generate efficiency summaries for all work centers.
     */
    public function generateWorkCenterReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonth() : Carbon::parse($filters['date_start']);
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $wcs = WorkCenter::where('tenant_id', $tenantId)->get();
        $reportData = [];

        foreach ($wcs as $wc) {
            $metrics = $this->oeeService->calculateForWorkCenter($tenantId, $wc->id, $start, $end);

            $reportData[] = [
                'work_center_id' => $wc->id,
                'name'           => $wc->name,
                'code'           => $wc->code,
                'oee'            => $metrics['oee'],
                'availability'   => $metrics['availability'],
                'performance'    => $metrics['performance'],
                'quality'        => $metrics['quality'],
            ];
        }

        return [
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'data'         => $reportData,
        ];
    }

    /**
     * Generate Downtime breakdown report.
     */
    public function generateDowntimeReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonth() : Carbon::parse($filters['date_start']);
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $query = ProductionMachineDowntime::with(['machine', 'creator'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('start_time', [$start, $end]);

        if (!empty($filters['machine_id'])) {
            $query->where('machine_id', $filters['machine_id']);
        }

        $downtimes = $query->orderBy('start_time', 'desc')->get();

        $categorySummary = DB::table('production_machine_downtimes')
            ->select('category', DB::raw('SUM(duration_minutes) as total_duration'), DB::raw('COUNT(*) as total_events'))
            ->where('tenant_id', $tenantId)
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('category')
            ->get();

        return [
            'period_start'     => $start->toDateString(),
            'period_end'       => $end->toDateString(),
            'downtimes'        => $downtimes,
            'category_summary' => $categorySummary,
        ];
    }
}
