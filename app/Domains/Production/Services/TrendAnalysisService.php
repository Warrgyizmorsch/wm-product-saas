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
                $metrics = ['oee' => 0.0, 'availability' => 0.0, 'performance' => 0.0, 'quality' => 0.0];
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

        foreach ($periodRange as $date) {
            $labels[] = $date->format('d M');

            $dayFilters = array_merge($filters, [
                'date_start' => $date->copy()->startOfDay()->toDateTimeString(),
                'date_end' => $date->copy()->endOfDay()->toDateTimeString(),
            ]);

            $downtimes[] = 0.0;
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
