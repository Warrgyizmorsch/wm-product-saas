<?php

namespace App\Domains\Production\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class TrendAnalysisService
{
    public function __construct(
        private readonly OeeCalculationService $oeeService,
        private readonly KpiCalculationService $kpiService
    ) {}

    /**
     * Get daily OEE trend for a machine or work center.
     */
    public function getOeeTrend(int $tenantId, string $period = 'daily', array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subDays(6) : Carbon::parse($filters['date_start']);
        $end = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $labels = [];
        $oeeData = [];
        $availData = [];
        $perfData = [];
        $qualData = [];

        $periodRange = CarbonPeriod::create($start, $period === 'daily' ? '1 day' : '1 week', $end);

        foreach ($periodRange as $date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $period === 'daily' ? $date->copy()->endOfDay() : $date->copy()->addWeek()->endOfDay();

            $labels[] = $date->format($period === 'daily' ? 'd M' : '\W\k W');

            if (! empty($filters['machine_id'])) {
                $metrics = $this->oeeService->calculateForMachine($tenantId, (int) $filters['machine_id'], $dayStart, $dayEnd);
            } elseif (! empty($filters['work_center_id'])) {
                $metrics = $this->oeeService->calculateForWorkCenter($tenantId, (int) $filters['work_center_id'], $dayStart, $dayEnd);
            } else {
                // Calculate average across all tenant machines for this date interval
                $machines = \Illuminate\Support\Facades\DB::table('production_machines')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->get();

                if ($machines->isNotEmpty()) {
                    $oeeSum = 0; $availSum = 0; $perfSum = 0; $qualSum = 0;
                    foreach ($machines as $m) {
                        $mOee = $this->oeeService->calculateForMachine($tenantId, $m->id, $dayStart, $dayEnd);
                        $oeeSum += $mOee['oee'];
                        $availSum += $mOee['availability'];
                        $perfSum += $mOee['performance'];
                        $qualSum += $mOee['quality'];
                    }
                    $cnt = $machines->count();
                    $metrics = [
                        'oee'          => round($oeeSum / $cnt, 2),
                        'availability' => round($availSum / $cnt, 2),
                        'performance'  => round($perfSum / $cnt, 2),
                        'quality'      => round($qualSum / $cnt, 2),
                    ];
                } else {
                    $metrics = ['oee' => 0.0, 'availability' => 0.0, 'performance' => 0.0, 'quality' => 0.0];
                }
            }

            $oeeData[] = $metrics['oee'];
            $availData[] = $metrics['availability'] ?? 0;
            $perfData[] = $metrics['performance'] ?? 0;
            $qualData[] = $metrics['quality'] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'OEE %',
                    'data' => $oeeData,
                ],
                [
                    'label' => 'Availability %',
                    'data' => $availData,
                ],
                [
                    'label' => 'Performance %',
                    'data' => $perfData,
                ],
                [
                    'label' => 'Quality %',
                    'data' => $qualData,
                ],
            ],
        ];
    }

    /**
     * Get production vs planned trend.
     */
    public function getProductionTrend(int $tenantId, string $period = 'daily', array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subDays(6) : Carbon::parse($filters['date_start']);
        $end = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $labels = [];
        $planned = [];
        $actual = [];

        $periodRange = CarbonPeriod::create($start, '1 day', $end);

        foreach ($periodRange as $date) {
            $labels[] = $date->format('d M');

            $dayFilters = array_merge($filters, [
                'date_start' => $date->copy()->startOfDay()->toDateTimeString(),
                'date_end' => $date->copy()->endOfDay()->toDateTimeString(),
            ]);

            $stats = $this->kpiService->getProductionSummary($tenantId, $dayFilters);
            $planned[] = $stats['planned_quantity'] ?? 0.0;
            $actual[] = $stats['actual_quantity'] ?? 0.0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Planned Quantity',
                    'data' => $planned,
                ],
                [
                    'label' => 'Actual Quantity',
                    'data' => $actual,
                ],
            ],
        ];
    }

    /**
     * Get downtime trend.
     */
    public function getDowntimeTrend(int $tenantId, string $period = 'daily', array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subDays(6) : Carbon::parse($filters['date_start']);
        $end = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $labels = [];
        $downtimes = [];

        $periodRange = CarbonPeriod::create($start, '1 day', $end);

        $machinesCount = \Illuminate\Support\Facades\DB::table('production_machines')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when(!empty($filters['work_center_id']), fn($q) => $q->where('work_center_id', $filters['work_center_id']))
            ->when(!empty($filters['machine_id']), fn($q) => $q->where('id', $filters['machine_id']))
            ->count();

        foreach ($periodRange as $date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $labels[] = $date->format('d M');

            $downMins = (float) \Illuminate\Support\Facades\DB::table('production_machine_downtimes')
                ->where('tenant_id', $tenantId)
                ->whereBetween('start_time', [$dayStart, $dayEnd])
                ->when(!empty($filters['machine_id']), fn($q) => $q->where('machine_id', $filters['machine_id']))
                ->when(!empty($filters['work_center_id']), function($q) use ($filters, $tenantId) {
                    $q->whereIn('machine_id', \Illuminate\Support\Facades\DB::table('production_machines')->where('tenant_id', $tenantId)->where('work_center_id', $filters['work_center_id'])->pluck('id'));
                })
                ->sum('duration_minutes');

            $plannedMins = max(480.0, $machinesCount * 480.0);
            $downtimes[] = $plannedMins > 0 ? round(($downMins / $plannedMins) * 100, 2) : 0.0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Downtime %',
                    'data' => $downtimes,
                ],
            ],
        ];
    }
}
