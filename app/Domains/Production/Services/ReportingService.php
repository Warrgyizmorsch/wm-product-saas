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
                'order.operations.workCenter',
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

        // Batch load RoutingOperationMaterials for all operations in these orders
        $routingOpIds = $reservations->flatMap(fn($res) => $res->order?->operations ?? collect())
            ->pluck('routing_operation_id')
            ->filter()
            ->unique();
        $routingMaterials = \App\Domains\Production\Models\RoutingOperationMaterial::whereIn('routing_operation_id', $routingOpIds)
            ->get();

        $rows = [];
        $totalPlannedCost = 0.0;
        $totalIssuedCost = 0.0;
        $totalConsumedCost = 0.0;
        $totalFloorBalanceCost = 0.0;
        $totalVarianceCost = 0.0;
        $uomGroups = [];

        foreach ($reservations as $res) {
            $order = $res->order;
            $plannedQty = (float) $res->quantity_planned;
            $issuedQty = (float) $res->quantity_issued;
            $varianceQty = $issuedQty - $plannedQty;
            $variancePct = $plannedQty > 0 ? round(($varianceQty / $plannedQty) * 100, 1) : 0.0;

            $unitCost = (float) ($res->product?->unit_cost ?? $res->product?->cost_price ?? 0.0);
            $plannedCost = $plannedQty * $unitCost;
            $issuedCost = $issuedQty * $unitCost;
            $varianceCost = $varianceQty * $unitCost;

            // Resolve assigned operation for this material
            $ops = $order?->operations ?? collect();
            $matchedRoutingMat = $routingMaterials->first(function ($rm) use ($ops, $res) {
                return $rm->material_id == $res->product_id && $ops->contains('routing_operation_id', $rm->routing_operation_id);
            });

            $matchedOp = null;
            if ($matchedRoutingMat) {
                $matchedOp = $ops->firstWhere('routing_operation_id', $matchedRoutingMat->routing_operation_id);
            }
            if (!$matchedOp && $ops->isNotEmpty()) {
                $matchedOp = $ops->first(); // Initial intake stage
            }

            $opName = $matchedOp ? "Op #{$matchedOp->sequence}: {$matchedOp->name}" : 'General Issue';
            $wcName = $matchedOp?->workCenter?->name ?? '—';

            // Calculate operation progress and actual consumption
            $orderPlanned = (float) ($order?->quantity_ordered ?? 0.0);
            $opTarget = (float) ($matchedOp?->target_produced_qty > 0 ? $matchedOp->target_produced_qty : $orderPlanned);
            $opProduced = (float) ($matchedOp?->quantity_produced ?? 0.0);
            $opProgress = $opTarget > 0 ? min(1.0, $opProduced / $opTarget) : 0.0;

            if ($matchedOp && (float) $matchedOp->quantity_consumed > 0) {
                $theoreticalConsumed = (float) $matchedOp->quantity_consumed;
            } else {
                $theoreticalConsumed = round($plannedQty * $opProgress, 4);
            }

            $consumedQty = $issuedQty > 0 ? min($issuedQty, $theoreticalConsumed) : 0.0;
            $floorBalance = max(0.0, $issuedQty - $consumedQty);
            $consumptionPct = $plannedQty > 0 ? min(100.0, round(($consumedQty / $plannedQty) * 100, 1)) : 0.0;

            $consumedCost = $consumedQty * $unitCost;
            $floorBalanceCost = $floorBalance * $unitCost;

            $totalPlannedCost += $plannedCost;
            $totalIssuedCost += $issuedCost;
            $totalConsumedCost += $consumedCost;
            $totalFloorBalanceCost += $floorBalanceCost;
            $totalVarianceCost += $varianceCost;

            $uomCode = $res->uom?->code ?? $res->product?->uom?->code ?? 'Units';
            if (!isset($uomGroups[$uomCode])) {
                $uomGroups[$uomCode] = ['planned' => 0.0, 'issued' => 0.0, 'consumed' => 0.0, 'floor_balance' => 0.0, 'variance' => 0.0];
            }
            $uomGroups[$uomCode]['planned'] += $plannedQty;
            $uomGroups[$uomCode]['issued'] += $issuedQty;
            $uomGroups[$uomCode]['consumed'] += $consumedQty;
            $uomGroups[$uomCode]['floor_balance'] += $floorBalance;
            $uomGroups[$uomCode]['variance'] += $varianceQty;

            $rows[] = [
                'id'                 => $res->id,
                'order_id'           => $res->production_order_id,
                'order_number'       => $res->order?->order_number ?? '—',
                'finished_good'      => $res->order?->product?->name ?? '—',
                'operation_name'     => $opName,
                'work_center'        => $wcName,
                'material_name'      => $res->product?->name ?? '—',
                'material_sku'       => $res->product?->sku ?? '—',
                'uom'                => $uomCode,
                'planned_qty'        => $plannedQty,
                'issued_qty'         => $issuedQty,
                'consumed_qty'       => $consumedQty,
                'floor_balance'      => $floorBalance,
                'consumption_pct'    => $consumptionPct,
                'variance_qty'       => $varianceQty,
                'variance_pct'       => $variancePct,
                'unit_cost'          => $unitCost,
                'planned_cost'       => $plannedCost,
                'issued_cost'        => $issuedCost,
                'consumed_cost'      => $consumedCost,
                'floor_balance_cost' => $floorBalanceCost,
                'variance_cost'      => $varianceCost,
            ];
        }

        $summary = [
            'total_items'              => count($rows),
            'total_planned_cost'       => $totalPlannedCost,
            'total_issued_cost'        => $totalIssuedCost,
            'total_consumed_cost'      => $totalConsumedCost,
            'total_floor_balance_cost' => $totalFloorBalanceCost,
            'total_variance_cost'      => $totalVarianceCost,
            'uom_groups'               => $uomGroups,
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

    /**
     * Generate a single Production Order Detail Report (Job Card / Shop Traveler).
     *
     * Returns a structured array with:
     *  - order: header fields
     *  - operations: each routing step with its work center, machine, timing & quantities
     *  - materials: per-component planned vs issued with cost variance
     *  - scraps: all scrap events with reason, operation, quantity
     *  - wip: current WIP location (which operation still has open qty)
     */
    public function generateOrderDetailReport(int $tenantId, int $orderId): array
    {
        $order = \App\Domains\Production\Models\ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with([
                'product.uom',
                'creator',
                'operations.workCenter',
                'operations.machine',
                'reservations.product.uom',
                'scraps.product',
                'scraps.operation',
                'wips',
            ])
            ->findOrFail($orderId);

        // ── 1. Order Header ───────────────────────────────────────────────────
        $planned  = (float) $order->quantity_ordered;
        $produced = (float) $order->quantity_produced;
        $scrapped = (float) $order->quantity_scrapped;
        $rejected = (float) $order->quantity_rejected;
        $attempted = $produced + $scrapped + $rejected;
        $completionPct = $planned > 0 ? min(100.0, round(($produced / $planned) * 100, 1)) : 0.0;
        $yieldPct      = $attempted > 0 ? round(($produced / $attempted) * 100, 1) : 100.0;

        $header = [
            'id'             => $order->id,
            'order_number'   => $order->order_number,
            'product_name'   => $order->product?->name ?? '—',
            'product_sku'    => $order->product?->sku ?? '—',
            'uom'            => $order->product?->uom?->code ?? 'Units',
            'status'         => $order->status,
            'production_model' => $order->production_model ?? 'pure_manufacturing',
            'planned_qty'    => $planned,
            'produced_qty'   => $produced,
            'scrapped_qty'   => $scrapped,
            'rejected_qty'   => $rejected,
            'completion_pct' => $completionPct,
            'yield_pct'      => $yieldPct,
            'start_date'     => $order->start_date?->toDateString() ?? '—',
            'end_date'       => $order->end_date?->toDateString() ?? '—',
            'actual_start'   => $order->actual_start_date?->toDateTimeString(),
            'actual_end'     => $order->actual_end_date?->toDateTimeString(),
            'created_by'     => $order->creator?->name ?? '—',
            'bom_id'         => $order->bom_id,
            'routing_id'     => $order->routing_id,
        ];

        // ── 2. Operation Stages ───────────────────────────────────────────────
        $ops = [];
        foreach ($order->operations as $op) {
            $timePlanned  = (float) ($op->total_time_planned ?? 0);
            $timeActual   = (float) (($op->setup_time_actual ?? 0) + ($op->processing_time_actual ?? 0));
            $efficiencyPct = $timePlanned > 0 ? round(($timePlanned / max($timeActual, 0.01)) * 100, 1) : null;

            $ops[] = [
                'id'              => $op->id,
                'sequence'        => $op->sequence,
                'operation_number'=> $op->operation_number,
                'name'            => $op->name,
                'work_center'     => $op->workCenter?->name ?? '—',
                'machine'         => $op->machine?->name ?? '—',
                'status'          => $op->status,
                'is_external'     => (bool) $op->is_external,
                'quality_required'=> (bool) $op->quality_required,
                'qty_produced'    => (float) $op->quantity_produced,
                'qty_rejected'    => (float) $op->quantity_rejected,
                'qty_scrapped'    => (float) $op->quantity_scrapped,
                'setup_planned'   => (float) $op->setup_time_planned,
                'setup_actual'    => (float) ($op->setup_time_actual ?? 0),
                'process_planned' => (float) $op->processing_time_planned,
                'process_actual'  => (float) ($op->processing_time_actual ?? 0),
                'total_planned'   => $timePlanned,
                'total_actual'    => $timeActual,
                'efficiency_pct'  => $efficiencyPct,
                'actual_start'    => $op->actual_start_time?->toDateTimeString(),
                'actual_end'      => $op->actual_end_time?->toDateTimeString(),
            ];
        }

        // ── 3. Material Consumption ───────────────────────────────────────────
        $routingOpIds = $order->operations->pluck('routing_operation_id')->filter()->unique();
        $routingMaterials = \App\Domains\Production\Models\RoutingOperationMaterial::whereIn('routing_operation_id', $routingOpIds)->get();

        $materials = [];
        $totalPlannedCost = 0.0;
        $totalIssuedCost  = 0.0;
        $totalConsumedCost = 0.0;
        $totalFloorBalanceCost = 0.0;

        foreach ($order->reservations as $res) {
            $plannedQty  = (float) $res->quantity_planned;
            $issuedQty   = (float) $res->quantity_issued;
            $reservedQty = (float) $res->quantity_reserved;
            $varQty      = $issuedQty - $plannedQty;
            $varPct      = $plannedQty > 0 ? round(($varQty / $plannedQty) * 100, 1) : 0.0;
            $unitCost    = (float) ($res->product?->unit_cost ?? $res->product?->cost_price ?? 0.0);
            $plannedCost = $plannedQty * $unitCost;
            $issuedCost  = $issuedQty  * $unitCost;

            // Match consuming operation
            $matchedRoutingMat = $routingMaterials->firstWhere('material_id', $res->product_id);
            $matchedOp = null;
            if ($matchedRoutingMat) {
                $matchedOp = $order->operations->firstWhere('routing_operation_id', $matchedRoutingMat->routing_operation_id);
            }
            if (!$matchedOp && $order->operations->isNotEmpty()) {
                $matchedOp = $order->operations->first(); // Initial intake stage
            }

            $opName = $matchedOp ? "Op #{$matchedOp->sequence}: {$matchedOp->name}" : 'General Issue';
            $wcName = $matchedOp?->workCenter?->name ?? '—';

            // Calculate operation progress and actual consumption
            $opTarget = (float) ($matchedOp?->target_produced_qty > 0 ? $matchedOp->target_produced_qty : $planned);
            $opProduced = (float) ($matchedOp?->quantity_produced ?? 0.0);
            $opProgress = $opTarget > 0 ? min(1.0, $opProduced / $opTarget) : 0.0;

            if ($matchedOp && (float) $matchedOp->quantity_consumed > 0) {
                $theoreticalConsumed = (float) $matchedOp->quantity_consumed;
            } else {
                $theoreticalConsumed = round($plannedQty * $opProgress, 4);
            }

            $consumedQty = $issuedQty > 0 ? min($issuedQty, $theoreticalConsumed) : 0.0;
            $floorBalance = max(0.0, $issuedQty - $consumedQty);
            $consumptionPct = $plannedQty > 0 ? min(100.0, round(($consumedQty / $plannedQty) * 100, 1)) : 0.0;

            $consumedCost = $consumedQty * $unitCost;
            $floorBalanceCost = $floorBalance * $unitCost;

            $totalPlannedCost += $plannedCost;
            $totalIssuedCost  += $issuedCost;
            $totalConsumedCost += $consumedCost;
            $totalFloorBalanceCost += $floorBalanceCost;

            $materials[] = [
                'material_name'      => $res->product?->name ?? '—',
                'material_sku'       => $res->product?->sku  ?? '—',
                'operation_name'     => $opName,
                'work_center'        => $wcName,
                'uom'                => $res->product?->uom?->code ?? 'Units',
                'planned_qty'        => $plannedQty,
                'reserved_qty'       => $reservedQty,
                'issued_qty'         => $issuedQty,
                'consumed_qty'       => $consumedQty,
                'floor_balance'      => $floorBalance,
                'consumption_pct'    => $consumptionPct,
                'variance_qty'       => $varQty,
                'variance_pct'       => $varPct,
                'unit_cost'          => $unitCost,
                'planned_cost'       => $plannedCost,
                'issued_cost'        => $issuedCost,
                'consumed_cost'      => $consumedCost,
                'floor_balance_cost' => $floorBalanceCost,
                'variance_cost'      => $issuedCost - $plannedCost,
            ];
        }

        $materialSummary = [
            'total_planned_cost'       => $totalPlannedCost,
            'total_issued_cost'        => $totalIssuedCost,
            'total_consumed_cost'      => $totalConsumedCost,
            'total_floor_balance_cost' => $totalFloorBalanceCost,
            'variance_cost'            => $totalIssuedCost - $totalPlannedCost,
        ];

        // ── 4. Scrap Events ───────────────────────────────────────────────────
        $scrapEvents = [];
        foreach ($order->scraps as $scrap) {
            $scrapEvents[] = [
                'id'            => $scrap->id,
                'product'       => $scrap->product?->name ?? $order->product?->name ?? '—',
                'product_sku'   => $scrap->product?->sku ?? '—',
                'operation'     => $scrap->operation?->name ?? '—',
                'quantity'      => (float) $scrap->quantity,
                'reason'        => $scrap->reason ?? '—',
                'recorded_at'   => $scrap->recorded_at?->toDateTimeString() ?? '—',
                'stock_posted'  => $scrap->isStockPosted(),
            ];
        }

        // ── 5. Current WIP Location ───────────────────────────────────────────
        // Find the WIP card(s) that still have available quantity (where units currently sit)
        $wipLocations = [];
        foreach ($order->wips as $wip) {
            if ((float) $wip->available_quantity > 0) {
                // Resolve the operation name from the routing_operation_id link
                $opName = '—';
                $wcName = '—';
                if ($wip->current_routing_operation_id) {
                    $matchedOp = $order->operations->first(function ($op) use ($wip) {
                        return $op->routing_operation_id == $wip->current_routing_operation_id
                            || $op->id == $wip->current_routing_operation_id;
                    });
                    $opName = $matchedOp?->name ?? '—';
                    $wcName = $matchedOp?->workCenter?->name ?? '—';
                }

                $wipLocations[] = [
                    'wip_stage'   => $wip->stage ?? $wip->status ?? '—',
                    'operation'   => $opName,
                    'work_center' => $wcName,
                    'available'   => (float) $wip->available_quantity,
                    'batch'       => $wip->productionBatch?->batch_number ?? '—',
                ];
            }
        }

        // ── 6. Cost Estimation vs Actual Cost Analysis ────────────────────────
        $costAnalysis = $this->costService->getCostAnalysis($order);
        $totVar = $costAnalysis['totals']['variance'] ?? 0;
        $costEstimation = [
            'material' => $costAnalysis['material'],
            'labor'    => $costAnalysis['labor'],
            'machine'  => $costAnalysis['machine'],
            'overhead' => $costAnalysis['overhead'],
            'totals'   => $costAnalysis['totals'],
            'total'    => $costAnalysis['totals'],
            'estimated' => [
                'material_cost' => $costAnalysis['material']['planned'] ?? 0,
                'labor_cost'    => $costAnalysis['labor']['planned'] ?? 0,
                'machine_cost'  => $costAnalysis['machine']['planned'] ?? 0,
                'overhead_cost' => $costAnalysis['overhead']['planned'] ?? 0,
                'total_cost'    => $costAnalysis['totals']['planned'] ?? 0,
            ],
            'actual' => [
                'material_cost' => $costAnalysis['material']['actual'] ?? 0,
                'labor_cost'    => $costAnalysis['labor']['actual'] ?? 0,
                'machine_cost'  => $costAnalysis['machine']['actual'] ?? 0,
                'overhead_cost' => $costAnalysis['overhead']['actual'] ?? 0,
                'total_cost'    => $costAnalysis['totals']['actual'] ?? 0,
            ],
            'variance' => [
                'material' => $costAnalysis['material']['variance'] ?? 0,
                'labor'    => $costAnalysis['labor']['variance'] ?? 0,
                'machine'  => $costAnalysis['machine']['variance'] ?? 0,
                'overhead' => $costAnalysis['overhead']['variance'] ?? 0,
            ],
            'net_variance'    => $totVar,
            'variance_status' => $totVar > 0 ? 'OVER BUDGET' : ($totVar < 0 ? 'UNDER BUDGET' : 'ON TARGET'),
        ];

        return [
            // period_start / period_end are required by the shared filter form in reports-detail.blade.php
            'period_start'    => now()->toDateString(),
            'period_end'      => now()->toDateString(),
            'generated_at'    => now()->toDateTimeString(),
            'order'           => $header,
            'operations'      => $ops,
            'materials'       => $materials,
            'material_summary'=> $materialSummary,
            'scrap_events'    => $scrapEvents,
            'wip_locations'   => $wipLocations,
            'cost_estimation' => $costEstimation,
        ];
    }

    /**
     * Generate Sales Order Tracking / Fulfillment Pipeline report.
     * Tracks: Sales Order -> Production Order (MO) -> Material Requisition -> Indent / PO -> Dispatch / Delivery
     */
    public function generateSalesOrderTrackingReport(int $tenantId, array $filters = []): array
    {
        $start = empty($filters['date_start']) ? Carbon::today()->subMonths(3)->startOfDay() : Carbon::parse($filters['date_start'])->startOfDay();
        $end   = empty($filters['date_end']) ? Carbon::today()->endOfDay() : Carbon::parse($filters['date_end'])->endOfDay();

        $query = \App\Domains\Sales\Models\SalesOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->with([
                'customer',
                'salesPerson',
                'items.product',
                'productionOrders',
                'materialRequirements.items.purchaseRequisition',
                'dispatches.items',
            ]);

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['order_number'])) {
            $query->where('sales_order_number', 'like', '%' . str_replace('SO-', '', $filters['order_number']) . '%');
        }

        $salesOrders = $query->orderBy('order_date', 'desc')->get();

        $rows = [];
        $totalOrders = $salesOrders->count();
        $totalOrderedQty = 0;
        $totalProducedQty = 0;
        $totalDeliveredQty = 0;
        $moCompletedCount = 0;
        $srNo = 1;

        foreach ($salesOrders as $so) {
            $soNumber = $so->sales_order_number;
            $soDate = $so->order_date ? $so->order_date->format('Y-m-d') : '—';
            $customerName = $so->customer?->name ?? '—';
            $salesPerson = $so->salesPerson?->name ?? '—';
            $deliveryDate = $so->shipment_date ? $so->shipment_date->format('Y-m-d') : '—';
            $soStatus = $so->status ?? 'Open';
            $approvalDate = $so->order_date ? $so->order_date->format('Y-m-d') : '—';

            $items = $so->items;
            if ($items->isEmpty()) {
                continue;
            }

            foreach ($items as $item) {
                $orderedQty = (float) ($item->quantity ?? 0);
                $totalOrderedQty += $orderedQty;

                // Match linked Production Order(s)
                $linkedMo = $so->productionOrders->first(function ($po) use ($item) {
                    return ($po->sales_order_item_id && $po->sales_order_item_id == $item->id)
                        || ($po->product_id == $item->product_id);
                });

                if ($linkedMo) {
                    $moNo = $linkedMo->order_number ?? $linkedMo->code ?? "MO-{$linkedMo->id}";
                    $moDate = $linkedMo->start_date ? $linkedMo->start_date->format('Y-m-d') : ($linkedMo->created_at ? $linkedMo->created_at->format('Y-m-d') : '—');
                    $moDoneQty = (float) $linkedMo->quantity_produced;
                    $moPendingQty = max(0, $orderedQty - $moDoneQty);
                    $moDoneDate = $linkedMo->actual_end_date ? $linkedMo->actual_end_date->format('Y-m-d') : ($linkedMo->completed_at ? $linkedMo->completed_at->format('Y-m-d') : '—');
                    $moStatus = ucfirst(str_replace('_', ' ', $linkedMo->status));

                    if (in_array($linkedMo->status, ['completed', 'closed']) || $moDoneQty >= $orderedQty) {
                        $moColor = 'success';
                        $moCompletedCount++;
                    } elseif ($moDoneQty > 0 || $linkedMo->status === 'in_progress') {
                        $moColor = 'warning';
                    } else {
                        $moColor = 'danger';
                    }
                } else {
                    $moNo = '—';
                    $moDate = '—';
                    $moDoneQty = 0;
                    $moPendingQty = $orderedQty;
                    $moDoneDate = '—';
                    $moStatus = 'Not Started';
                    $moColor = 'danger';
                }

                $totalProducedQty += $moDoneQty;

                // Match Material Requisition & Purchase Requisition (Indent)
                $matReqItem = null;
                $matReq = null;
                foreach ($so->materialRequirements as $mr) {
                    $foundItem = $mr->items->first(fn($mri) => $mri->sales_order_item_id == $item->id || $mri->product_id == $item->product_id);
                    if ($foundItem) {
                        $matReqItem = $foundItem;
                        $matReq = $mr;
                        break;
                    }
                }

                $reqNo = $matReq?->requirement_number ?? '—';
                $indentNo = $matReqItem?->purchaseRequisition?->requisition_number ?? ($matReqItem?->purchase_requisition_id ? "PR-{$matReqItem->purchase_requisition_id}" : '—');
                $indentDate = $matReqItem?->purchaseRequisition?->requisition_date ? $matReqItem->purchaseRequisition->requisition_date->format('Y-m-d') : ($matReq?->requirement_date ? $matReq->requirement_date->format('Y-m-d') : '—');

                if ($matReqItem && in_array(strtolower($matReqItem->status), ['ready', 'completed', 'approved'])) {
                    $reqColor = 'success';
                } elseif ($matReqItem && in_array(strtolower($matReqItem->status), ['partial', 'reserved', 'in_progress'])) {
                    $reqColor = 'warning';
                } elseif ($matReq) {
                    $reqColor = 'warning';
                } else {
                    $reqColor = 'secondary';
                }

                // Match Purchase Order (PO)
                $poNo = '—';
                $supplierName = '—';
                $poDate = '—';
                $poColor = 'secondary';

                if ($matReqItem?->purchaseRequisition) {
                    $pr = $matReqItem->purchaseRequisition;
                    $po = \App\Domains\Purchase\Models\PurchaseOrder::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('purchase_requisition_id', $pr->id)
                        ->with('vendor')
                        ->first();
                    if ($po) {
                        $poNo = $po->purchase_order_number ?? "PO-{$po->id}";
                        $supplierName = $po->vendor?->name ?? '—';
                        $poDate = $po->date ? $po->date->format('Y-m-d') : '—';
                        $poColor = in_array(strtolower($po->status), ['approved', 'completed', 'received']) ? 'success' : 'warning';
                    }
                }

                // Match Dispatches / Deliveries
                $dispatchedQty = 0;
                $lastDeliveryDate = '—';
                foreach ($so->dispatches as $dispatch) {
                    $dItem = $dispatch->items->first(fn($di) => $di->product_id == $item->product_id);
                    if ($dItem) {
                        $dispatchedQty += (float) ($dItem->quantity_dispatched ?? 0);
                        if ($dispatch->delivered_at) {
                            $lastDeliveryDate = $dispatch->delivered_at->format('Y-m-d');
                        } elseif ($dispatch->dispatch_date) {
                            $lastDeliveryDate = $dispatch->dispatch_date->format('Y-m-d');
                        }
                    }
                }
                if ($dispatchedQty == 0 && $matReqItem?->dispatched_qty > 0) {
                    $dispatchedQty = (float) $matReqItem->dispatched_qty;
                }

                $totalDeliveredQty += $dispatchedQty;
                $pendingDeliveryQty = max(0, $orderedQty - $dispatchedQty);

                if ($dispatchedQty >= $orderedQty && $orderedQty > 0) {
                    $deliveryColor = 'success';
                } elseif ($dispatchedQty > 0) {
                    $deliveryColor = 'warning';
                } else {
                    $deliveryColor = 'danger';
                }

                $rows[] = [
                    'sr_no'                  => $srNo++,
                    'sales_person'           => $salesPerson,
                    'sales_order_no'         => $soNumber,
                    'sales_order_id'         => $so->id,
                    'sales_order_date'       => $soDate,
                    'customer_name'          => $customerName,
                    'product_name'           => $item->product?->name ?? $item->item_name ?? 'Item #' . $item->id,
                    'product_sku'            => $item->product?->sku ?? $item->product?->code ?? '—',
                    'so_qty'                 => $orderedQty,
                    'customer_delivery_date' => $deliveryDate,
                    'status'                 => $soStatus,
                    'approval_date'          => $approvalDate,
                    // MO (Production)
                    'mo_no'                  => $moNo,
                    'mo_id'                  => $linkedMo?->id,
                    'mo_date'                => $moDate,
                    'mo_status'              => $moStatus,
                    'mo_done_qty'            => $moDoneQty,
                    'mo_done_date'           => $moDoneDate,
                    'mo_pending_qty'         => $moPendingQty,
                    'mo_color'               => $moColor,
                    // Requisition & Indent
                    'requisition_no'         => $reqNo,
                    'indent_no'              => $indentNo,
                    'indent_date'            => $indentDate,
                    'req_color'              => $reqColor,
                    // Purchase Order
                    'po_no'                  => $poNo,
                    'supplier_name'          => $supplierName,
                    'po_date'                => $poDate,
                    'po_color'               => $poColor,
                    // Delivery
                    'delivered_qty'          => $dispatchedQty,
                    'delivered_date'         => $lastDeliveryDate,
                    'pending_qty'            => $pendingDeliveryQty,
                    'delivery_color'         => $deliveryColor,
                ];
            }
        }

        $fulfillmentPct = $totalOrderedQty > 0 ? round(($totalDeliveredQty / $totalOrderedQty) * 100, 1) : 0;
        $productionPct  = $totalOrderedQty > 0 ? round(($totalProducedQty / $totalOrderedQty) * 100, 1) : 0;

        return [
            'period_start'         => $start->toDateString(),
            'period_end'           => $end->toDateString(),
            'total_orders'         => $totalOrders,
            'total_ordered_qty'    => $totalOrderedQty,
            'total_produced_qty'   => $totalProducedQty,
            'total_delivered_qty'  => $totalDeliveredQty,
            'total_pending_qty'    => max(0, $totalOrderedQty - $totalDeliveredQty),
            'production_pct'       => $productionPct,
            'fulfillment_pct'      => $fulfillmentPct,
            'data'                 => $rows,
        ];
    }
}
