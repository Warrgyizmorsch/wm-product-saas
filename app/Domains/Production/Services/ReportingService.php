<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionCostVarianceService;
use App\Domains\Production\Services\ProductionCostAdjustmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    private readonly ProductionCostVarianceService $costService;
    private readonly ProductionCostAdjustmentService $adjustmentService;

    public function __construct(
        private readonly OeeCalculationService $oeeService,
        private readonly KpiCalculationService $kpiService,
        ?ProductionCostVarianceService $costService = null,
        ?ProductionCostAdjustmentService $adjustmentService = null,
    ) {
        $this->costService = $costService ?? app(ProductionCostVarianceService::class);
        $this->adjustmentService = $adjustmentService ?? app(ProductionCostAdjustmentService::class);
    }

    /**
     * Generate OEE and usage stats for all machines.
     */
    public function generateMachineReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonth() : Carbon::parse($filters['date_start']);
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end']);

        $query = Machine::where('tenant_id', $tenantId);
        if (!empty($filters['machine_id'])) {
            $query->where('id', $filters['machine_id']);
        }
        $machines = $query->get();
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

        $query = WorkCenter::where('tenant_id', $tenantId);
        if (!empty($filters['work_center_id'])) {
            $query->where('id', $filters['work_center_id']);
        }
        $wcs = $query->get();
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

    /**
     * Generate Production Order Summary and Output Report.
     */
    public function generateProductionOrderReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonth()->startOfDay() : Carbon::parse($filters['date_start'])->startOfDay();
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end'])->endOfDay();

        $query = ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('created_at', [$start, $end]);
            })
            ->with(['product.uom']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (!empty($filters['order_id'])) {
            $query->where('id', $filters['order_id']);
        }
        if (!empty($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . trim($filters['order_number']) . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $rows = [];
        $totalPlanned = 0.0;
        $totalProduced = 0.0;
        $totalScrapped = 0.0;
        $totalRejected = 0.0;

        foreach ($orders as $order) {
            $planned = (float) $order->quantity_ordered;
            $produced = (float) $order->quantity_produced;
            $scrapped = (float) $order->quantity_scrapped;
            $rejected = (float) $order->quantity_rejected;

            $totalPlanned += $planned;
            $totalProduced += $produced;
            $totalScrapped += $scrapped;
            $totalRejected += $rejected;

            $completionPct = $planned > 0 ? min(100.0, round(($produced / $planned) * 100, 1)) : 0.0;
            $totalAttempted = $produced + $scrapped + $rejected;
            $yieldPct = $totalAttempted > 0 ? round(($produced / $totalAttempted) * 100, 1) : 100.0;

            $durationDays = null;
            if ($order->actual_start_date && $order->actual_end_date) {
                $durationDays = round($order->actual_start_date->diffInHours($order->actual_end_date) / 24, 1);
            } elseif ($order->start_date && $order->end_date) {
                $durationDays = Carbon::parse($order->start_date)->diffInDays(Carbon::parse($order->end_date));
            }

            $rows[] = [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'product_name'   => $order->product?->name ?? '—',
                'product_sku'    => $order->product?->sku ?? '—',
                'uom'            => $order->product?->uom?->code ?? 'Units',
                'planned_qty'    => $planned,
                'produced_qty'   => $produced,
                'scrapped_qty'   => $scrapped,
                'rejected_qty'   => $rejected,
                'completion_pct' => $completionPct,
                'yield_pct'      => $yieldPct,
                'start_date'     => $order->start_date ? Carbon::parse($order->start_date)->toDateString() : '—',
                'end_date'       => $order->end_date ? Carbon::parse($order->end_date)->toDateString() : '—',
                'actual_start'   => $order->actual_start_date ? $order->actual_start_date->toDateTimeString() : null,
                'actual_end'     => $order->actual_end_date ? $order->actual_end_date->toDateTimeString() : null,
                'duration'       => $durationDays !== null ? $durationDays . ' days' : '—',
                'status'         => $order->status,
            ];
        }

        $overallAttempted = $totalProduced + $totalScrapped + $totalRejected;
        $summary = [
            'total_orders'           => count($rows),
            'total_planned_qty'      => $totalPlanned,
            'total_produced_qty'     => $totalProduced,
            'total_scrapped_qty'     => $totalScrapped,
            'total_rejected_qty'     => $totalRejected,
            'overall_completion_pct' => $totalPlanned > 0 ? min(100.0, round(($totalProduced / $totalPlanned) * 100, 1)) : 0.0,
            'overall_yield_pct'      => $overallAttempted > 0 ? round(($totalProduced / $overallAttempted) * 100, 1) : 100.0,
        ];

        return [
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'data'         => $rows,
            'summary'      => $summary,
        ];
    }

    /**
     * Generate Material Consumption and Variance Report.
     */
    public function generateMaterialConsumptionReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonth()->startOfDay() : Carbon::parse($filters['date_start'])->startOfDay();
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end'])->endOfDay();

        $query = ProductionOrderReservation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereHas('order', function ($q) use ($tenantId, $start, $end, $filters) {
                $q->where('tenant_id', $tenantId)
                    ->where(function ($sub) use ($start, $end) {
                        $sub->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                            ->orWhereBetween('created_at', [$start, $end]);
                    });
                if (!empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->with([
                'order.product',
                'product.uom',
                'uom',
            ]);

        if (!empty($filters['order_id'])) {
            $query->where('production_order_id', $filters['order_id']);
        }
        if (!empty($filters['product_id'])) {
            $query->whereHas('order', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }
        if (!empty($filters['material_id'])) {
            $query->where('product_id', $filters['material_id']);
        }

        $reservations = $query->get();

        $rows = [];
        $totalPlannedCost = 0.0;
        $totalIssuedCost = 0.0;
        $totalVarianceCost = 0.0;
        $uomGroups = [];

        foreach ($reservations as $res) {
            $plannedQty = (float) $res->quantity_planned;
            $issuedQty = (float) $res->quantity_issued; // Canonical reservation-level issued quantity
            $varianceQty = $issuedQty - $plannedQty;
            $variancePct = $plannedQty > 0 ? round(($varianceQty / $plannedQty) * 100, 1) : 0.0;

            $unitCost = (float) ($res->product?->unit_cost ?? $res->product?->cost_price ?? 0.0);
            $plannedCost = $plannedQty * $unitCost;
            $issuedCost = $issuedQty * $unitCost;
            $varianceCost = $varianceQty * $unitCost;

            $totalPlannedCost += $plannedCost;
            $totalIssuedCost += $issuedCost;
            $totalVarianceCost += $varianceCost;

            $uomCode = $res->uom?->code ?? $res->product?->uom?->code ?? 'Units';
            if (!isset($uomGroups[$uomCode])) {
                $uomGroups[$uomCode] = ['planned' => 0.0, 'issued' => 0.0, 'variance' => 0.0];
            }
            $uomGroups[$uomCode]['planned'] += $plannedQty;
            $uomGroups[$uomCode]['issued'] += $issuedQty;
            $uomGroups[$uomCode]['variance'] += $varianceQty;

            $rows[] = [
                'id'             => $res->id,
                'order_id'       => $res->production_order_id,
                'order_number'   => $res->order?->order_number ?? '—',
                'finished_good'  => $res->order?->product?->name ?? '—',
                'material_name'  => $res->product?->name ?? '—',
                'material_sku'   => $res->product?->sku ?? '—',
                'uom'            => $uomCode,
                'planned_qty'    => $plannedQty,
                'issued_qty'     => $issuedQty,
                'variance_qty'   => $varianceQty,
                'variance_pct'   => $variancePct,
                'unit_cost'      => $unitCost,
                'planned_cost'   => $plannedCost,
                'issued_cost'    => $issuedCost,
                'variance_cost'  => $varianceCost,
            ];
        }

        $summary = [
            'total_items'         => count($rows),
            'total_planned_cost'  => $totalPlannedCost,
            'total_issued_cost'   => $totalIssuedCost,
            'total_variance_cost' => $totalVarianceCost,
            'uom_groups'          => $uomGroups,
        ];

        return [
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'data'         => $rows,
            'summary'      => $summary,
        ];
    }

    /**
     * Generate Production Cost and Variance Report.
     */
    public function generateCostVarianceReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonth()->startOfDay() : Carbon::parse($filters['date_start'])->startOfDay();
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end'])->endOfDay();

        $query = ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('created_at', [$start, $end]);
            })
            ->with([
                'product',
                'reservations.product',
                'issues.product',
                'operations.routingOperation',
                'operations.workCenter',
                'costAdjustments',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (!empty($filters['order_id'])) {
            $query->where('id', $filters['order_id']);
        }
        if (!empty($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . trim($filters['order_number']) . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $rows = [];
        $totPlannedCost = 0.0;
        $totActualCost = 0.0;
        $totVariance = 0.0;
        $totMaterialPlanned = 0.0;
        $totMaterialActual = 0.0;
        $totLaborPlanned = 0.0;
        $totLaborActual = 0.0;
        $totMachinePlanned = 0.0;
        $totMachineActual = 0.0;
        $totOverheadPlanned = 0.0;
        $totOverheadActual = 0.0;
        $totAdjustments = 0.0;

        foreach ($orders as $order) {
            $costs = $this->costService->getCostAnalysis($order);
            $finalSummary = $this->adjustmentService->getFinalCostingSummary($order, $costs);

            $plannedTotal = (float) ($costs['totals']['planned'] ?? 0.0);
            $actualTotal = (float) ($finalSummary['totals']['final'] ?? 0.0);
            $variance = $actualTotal - $plannedTotal;
            $variancePct = $plannedTotal > 0 ? round(($variance / $plannedTotal) * 100, 1) : 0.0;
            $adjustments = (float) ($finalSummary['totals']['manual'] ?? 0.0);

            $matPlanned = (float) ($costs['material']['planned'] ?? 0.0);
            $matActual = (float) ($finalSummary['material']['final'] ?? 0.0);
            $laborPlanned = (float) ($costs['labor']['planned'] ?? 0.0);
            $laborActual = (float) ($finalSummary['labor']['final'] ?? 0.0);
            $machPlanned = (float) ($costs['machine']['planned'] ?? 0.0);
            $machActual = (float) ($finalSummary['machine']['final'] ?? 0.0);
            $ovhPlanned = (float) ($costs['overhead']['planned'] ?? 0.0);
            $ovhActual = (float) ($finalSummary['overhead']['final'] ?? 0.0);

            $totPlannedCost += $plannedTotal;
            $totActualCost += $actualTotal;
            $totVariance += $variance;
            $totMaterialPlanned += $matPlanned;
            $totMaterialActual += $matActual;
            $totLaborPlanned += $laborPlanned;
            $totLaborActual += $laborActual;
            $totMachinePlanned += $machPlanned;
            $totMachineActual += $machActual;
            $totOverheadPlanned += $ovhPlanned;
            $totOverheadActual += $ovhActual;
            $totAdjustments += $adjustments;

            $rows[] = [
                'id'                   => $order->id,
                'order_number'         => $order->order_number,
                'product_name'         => $order->product?->name ?? '—',
                'product_sku'          => $order->product?->sku ?? '—',
                'status'               => $order->status,
                'planned_cost'         => $plannedTotal,
                'actual_material_cost' => $matActual,
                'actual_labor_cost'    => $laborActual,
                'actual_machine_cost'  => $machActual,
                'actual_overhead_cost' => $ovhActual,
                'adjustments'          => $adjustments,
                'actual_total_cost'    => $actualTotal,
                'variance_amount'      => $variance,
                'variance_pct'         => $variancePct,
            ];
        }

        $summary = [
            'total_orders'         => count($rows),
            'total_planned_cost'   => $totPlannedCost,
            'total_actual_cost'    => $totActualCost,
            'total_variance'       => $totVariance,
            'overall_variance_pct' => $totPlannedCost > 0 ? round(($totVariance / $totPlannedCost) * 100, 1) : 0.0,
            'material_planned'     => $totMaterialPlanned,
            'material_actual'      => $totMaterialActual,
            'labor_planned'        => $totLaborPlanned,
            'labor_actual'         => $totLaborActual,
            'machine_planned'      => $totMachinePlanned,
            'machine_actual'       => $totMachineActual,
            'overhead_planned'     => $totOverheadPlanned,
            'overhead_actual'      => $totOverheadActual,
            'total_adjustments'    => $totAdjustments,
        ];

        return [
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'data'         => $rows,
            'summary'      => $summary,
        ];
    }
}
