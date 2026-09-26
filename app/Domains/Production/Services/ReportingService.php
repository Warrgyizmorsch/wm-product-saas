<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
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

    /**
     * Generate Daily Production Report (DPR).
     *
     * Authoritative source: production_order_progress_logs
     * Timezone: Uses tenant-configured timezone or fallback to app.timezone.
     * Semantics:
     *   - Distinguishes Finished Goods (FG), Semi-Finished Goods (SFG), Components, and in-process stages.
     *   - Never sums intermediate operation progress as Finished Goods output.
     *   - Yield is calculated at the proper product/stage level.
     *
     * @param int $tenantId
     * @param array $filters
     * @return array
     */
    public function generateDailyProductionReport(int $tenantId, array $filters = []): array
    {
        $tz = tenant()?->timezone ?: config('app.timezone', 'UTC');

        $start = empty($filters['date_start'])
            ? Carbon::now($tz)->subMonth()->startOfDay()
            : Carbon::parse($filters['date_start'], $tz)->startOfDay();

        $end = empty($filters['date_end'])
            ? Carbon::now($tz)->endOfDay()
            : Carbon::parse($filters['date_end'], $tz)->endOfDay();

        // Query production_order_progress_logs as authoritative event source
        $query = ProductionOrderProgressLog::where('tenant_id', $tenantId)
            ->whereBetween('recorded_at', [
                $start->copy()->setTimezone(config('app.timezone', 'UTC')),
                $end->copy()->setTimezone(config('app.timezone', 'UTC'))
            ]);

        // Filter: Specific Production Order
        if (!empty($filters['order_id'])) {
            $query->where('production_order_id', $filters['order_id']);
        }

        // Filter: Production Order Number substring
        if (!empty($filters['order_number'])) {
            $query->whereHas('order', function ($q) use ($filters) {
                $q->where('order_number', 'like', '%' . trim($filters['order_number']) . '%');
            });
        }

        // Filter: Specific Product / Finished Good
        if (!empty($filters['product_id'])) {
            $query->whereHas('order', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        // Filter: Specific Machine (direct on log or via operation)
        if (!empty($filters['machine_id'])) {
            $machineId = (int) $filters['machine_id'];
            $query->where(function ($q) use ($machineId) {
                $q->where('machine_id', $machineId)
                    ->orWhereHas('operation', function ($opQ) use ($machineId) {
                        $opQ->where('machine_id', $machineId)
                            ->orWhere('machine_used_id', $machineId);
                    });
            });
        }

        // Filter: Specific Work Center (via operation or machine)
        if (!empty($filters['work_center_id'])) {
            $wcId = (int) $filters['work_center_id'];
            $query->where(function ($q) use ($wcId) {
                $q->whereHas('operation', fn($opQ) => $opQ->where('work_center_id', $wcId))
                    ->orWhereHas('machine', fn($mQ) => $mQ->where('work_center_id', $wcId));
            });
        }

        // Eager load necessary relationships including order operations for hierarchy resolution
        $logs = $query->with([
            'order' => fn($q) => $q->withoutGlobalScopes()->with(['product.uom', 'operations']),
            'operation.workCenter',
            'operation.machine',
            'operation.machineUsed',
            'operation.sourceProduct.uom',
            'machine.workCenter',
            'user',
            'batch',
        ])
        ->orderBy('recorded_at', 'asc')
        ->get();

        $fgProduced = 0.0;
        $sfgProduced = 0.0;
        $componentProduced = 0.0;
        $totalEventProcessedQty = 0.0;

        $fgRejected = 0.0;
        $fgScrapped = 0.0;
        $totalRejectedAll = 0.0;
        $totalScrappedAll = 0.0;

        $totalRunMinutes = 0.0;
        $totalSetupMinutes = 0.0;
        $distinctOrders = [];
        $distinctWorkCenters = [];
        $distinctMachines = [];

        $dailyGroups = [];
        $wcMachineGroups = [];
        $productOutputGroups = [];
        $detailedLogs = [];

        foreach ($logs as $log) {
            $carbonRecorded = $log->recorded_at ? Carbon::parse($log->recorded_at)->setTimezone($tz) : null;
            $dateStr = $carbonRecorded ? $carbonRecorded->toDateString() : 'Unknown';
            $timeStr = $carbonRecorded ? $carbonRecorded->format('H:i') : '—';

            $goodQty  = (float) $log->quantity_produced;
            $rejQty   = (float) $log->quantity_rejected;
            $scrapQty = (float) $log->quantity_scrapped;
            $runMin   = (float) $log->run_minutes_logged;
            $setupMin = (float) $log->setup_minutes_logged;

            $totalEventProcessedQty += $goodQty;
            $totalRejectedAll += $rejQty;
            $totalScrappedAll += $scrapQty;
            $totalRunMinutes += $runMin;
            $totalSetupMinutes += $setupMin;

            if ($log->production_order_id) {
                $distinctOrders[$log->production_order_id] = true;
            }

            // Resolve target product and semantic output classification
            $order = $log->order;
            $op = $log->operation;
            $product = $this->resolveProductForLog($log);
            $outputType = $this->resolveOutputType($product, $op, $order);
            $isTerminal = $op ? $this->isTerminalOperationForProduct($op, $order) : true;

            // Attribute physical output: only terminal operations yield net product output
            if ($outputType === 'fg') {
                $fgRejected += $rejQty;
                $fgScrapped += $scrapQty;
                if ($isTerminal) {
                    $fgProduced += $goodQty;
                }
            } elseif ($outputType === 'sfg') {
                if ($isTerminal) {
                    $sfgProduced += $goodQty;
                }
            } elseif ($outputType === 'component') {
                if ($isTerminal) {
                    $componentProduced += $goodQty;
                }
            }

            // Resolve Machine
            $machine = $log->machine ?? $op?->machine ?? $op?->machineUsed;
            $machineId = $machine?->id;
            $machineName = $machine?->name ?? 'Unassigned Machine';
            $machineCode = $machine?->code ?? '—';
            if ($machineId) {
                $distinctMachines[$machineId] = true;
            }

            // Resolve Work Center (from operation or machine)
            $workCenter = $op?->workCenter ?? $machine?->workCenter;
            $wcId = $workCenter?->id;
            $wcName = $workCenter?->name ?? 'Unassigned Work Center';
            $wcCode = $workCenter?->code ?? '—';
            if ($wcId) {
                $distinctWorkCenters[$wcId] = true;
            }

            // 1. Group by Operational Date
            if (!isset($dailyGroups[$dateStr])) {
                $dailyGroups[$dateStr] = [
                    'date'                    => $dateStr,
                    'active_orders'           => [],
                    'fg_output'               => 0.0,
                    'sfg_output'              => 0.0,
                    'component_output'        => 0.0,
                    'operation_events_count'  => 0,
                    'good_qty_processed'      => 0.0,
                    'fg_rejected'             => 0.0,
                    'fg_scrapped'             => 0.0,
                    'rejected_qty'            => 0.0,
                    'scrapped_qty'            => 0.0,
                    'run_minutes'             => 0.0,
                    'setup_minutes'           => 0.0,
                ];
            }
            if ($log->production_order_id) {
                $dailyGroups[$dateStr]['active_orders'][$log->production_order_id] = true;
            }
            $dailyGroups[$dateStr]['operation_events_count']++;
            $dailyGroups[$dateStr]['good_qty_processed'] += $goodQty;
            $dailyGroups[$dateStr]['rejected_qty'] += $rejQty;
            $dailyGroups[$dateStr]['scrapped_qty'] += $scrapQty;
            $dailyGroups[$dateStr]['run_minutes'] += $runMin;
            $dailyGroups[$dateStr]['setup_minutes'] += $setupMin;

            if ($outputType === 'fg') {
                $dailyGroups[$dateStr]['fg_rejected'] += $rejQty;
                $dailyGroups[$dateStr]['fg_scrapped'] += $scrapQty;
                if ($isTerminal) {
                    $dailyGroups[$dateStr]['fg_output'] += $goodQty;
                }
            } elseif ($outputType === 'sfg' && $isTerminal) {
                $dailyGroups[$dateStr]['sfg_output'] += $goodQty;
            } elseif ($outputType === 'component' && $isTerminal) {
                $dailyGroups[$dateStr]['component_output'] += $goodQty;
            }

            // 2. Product-Level Output Grouping (for terminal output gates)
            $productIdKey = ($outputType) . '_' . ($product?->id ?? 'unknown');
            if (!isset($productOutputGroups[$productIdKey])) {
                $typeLabels = [
                    'fg'        => 'Finished Good',
                    'sfg'       => 'Semi-Finished (SFG)',
                    'component' => 'Component',
                ];
                $productOutputGroups[$productIdKey] = [
                    'output_type'       => $outputType,
                    'output_type_label' => $typeLabels[$outputType] ?? 'Finished Good',
                    'product_id'        => $product?->id,
                    'product_name'      => $product?->name ?? '—',
                    'product_sku'       => $product?->sku ?? '—',
                    'uom'               => $product?->uom?->code ?? 'Units',
                    'output_qty'        => 0.0,
                    'in_process_qty'    => 0.0,
                    'rejected_qty'      => 0.0,
                    'scrapped_qty'      => 0.0,
                    'events_count'      => 0,
                ];
            }
            $productOutputGroups[$productIdKey]['events_count']++;
            $productOutputGroups[$productIdKey]['rejected_qty'] += $rejQty;
            $productOutputGroups[$productIdKey]['scrapped_qty'] += $scrapQty;
            if ($isTerminal) {
                $productOutputGroups[$productIdKey]['output_qty'] += $goodQty;
            } else {
                $productOutputGroups[$productIdKey]['in_process_qty'] += $goodQty;
            }

            // 3. Group by Work Center & Machine
            $groupKey = ($wcId ?? 'none') . '_' . ($machineId ?? 'none');
            if (!isset($wcMachineGroups[$groupKey])) {
                $wcMachineGroups[$groupKey] = [
                    'work_center_id'   => $wcId,
                    'work_center_name' => $wcName,
                    'work_center_code' => $wcCode,
                    'machine_id'       => $machineId,
                    'machine_name'     => $machineName,
                    'machine_code'     => $machineCode,
                    'good_qty'         => 0.0,
                    'rejected_qty'     => 0.0,
                    'scrapped_qty'     => 0.0,
                    'run_minutes'      => 0.0,
                    'setup_minutes'    => 0.0,
                    'events_count'     => 0,
                ];
            }
            $wcMachineGroups[$groupKey]['good_qty'] += $goodQty;
            $wcMachineGroups[$groupKey]['rejected_qty'] += $rejQty;
            $wcMachineGroups[$groupKey]['scrapped_qty'] += $scrapQty;
            $wcMachineGroups[$groupKey]['run_minutes'] += $runMin;
            $wcMachineGroups[$groupKey]['setup_minutes'] += $setupMin;
            $wcMachineGroups[$groupKey]['events_count']++;

            // 4. Detailed Operation / Production Event row
            $opDesc = $op ? ("Op #{$op->sequence}: {$op->name}") : '—';
            $logAttempted = $goodQty + $rejQty + $scrapQty;
            $logYield = $logAttempted > 0 ? round(($goodQty / $logAttempted) * 100, 1) : 100.0;
            $stageRole = $isTerminal ? 'Terminal Output' : 'Process Stage';

            $detailedLogs[] = [
                'id'                => $log->id,
                'date'              => $dateStr,
                'time'              => $timeStr,
                'recorded_at'       => $log->recorded_at,
                'order_id'          => $order?->id,
                'order_number'      => $order?->order_number ?? '—',
                'product_name'      => $product?->name ?? '—',
                'product_sku'       => $product?->sku ?? '—',
                'uom'               => $product?->uom?->code ?? 'Units',
                'output_type'       => $outputType,
                'output_type_label' => strtoupper($outputType),
                'stage_role'        => $stageRole,
                'is_terminal'       => $isTerminal,
                'operation_name'    => $opDesc,
                'work_center'       => $wcName,
                'work_center_code'  => $wcCode,
                'machine'           => $machineName,
                'machine_code'      => $machineCode,
                'batch_number'      => $log->batch?->batch_number ?? '—',
                'good_qty'          => $goodQty,
                'rejected_qty'      => $rejQty,
                'scrapped_qty'      => $scrapQty,
                'yield_pct'         => $logYield,
                'run_minutes'       => $runMin,
                'run_hours'         => round($runMin / 60, 2),
                'setup_minutes'     => $setupMin,
                'setup_hours'       => round($setupMin / 60, 2),
                'operator'          => $log->user?->name ?? 'Operator',
                'remarks'           => $log->remarks ?: '—',
            ];
        }

        // Format Daily Breakdown table rows
        $dailyBreakdown = [];
        foreach ($dailyGroups as $date => $d) {
            $fgAttempted = $d['fg_output'] + $d['fg_rejected'] + $d['fg_scrapped'];
            $fgYield = $fgAttempted > 0 ? round(($d['fg_output'] / $fgAttempted) * 100, 1) : 100.0;

            $dailyBreakdown[] = [
                'date'                   => $date,
                'active_orders_count'    => count($d['active_orders']),
                'fg_output'              => $d['fg_output'],
                'sfg_output'             => $d['sfg_output'],
                'component_output'       => $d['component_output'],
                'operation_events_count' => $d['operation_events_count'],
                'good_qty'               => $d['fg_output'], // Alias for backward compatibility
                'good_qty_processed'     => $d['good_qty_processed'],
                'rejected_qty'           => $d['rejected_qty'],
                'scrapped_qty'           => $d['scrapped_qty'],
                'yield_pct'              => $fgYield,
                'fg_yield_pct'           => $fgYield,
                'run_minutes'            => $d['run_minutes'],
                'run_hours'              => round($d['run_minutes'] / 60, 2),
                'setup_minutes'          => $d['setup_minutes'],
                'setup_hours'            => round($d['setup_minutes'] / 60, 2),
            ];
        }
        usort($dailyBreakdown, fn($a, $b) => strcmp($a['date'], $b['date']));

        // Format Product Output breakdown
        $productOutputs = [];
        foreach ($productOutputGroups as $p) {
            $att = $p['output_qty'] + $p['rejected_qty'] + $p['scrapped_qty'];
            $p['yield_pct'] = $att > 0 ? round(($p['output_qty'] / $att) * 100, 1) : 100.0;
            $productOutputs[] = $p;
        }
        usort($productOutputs, function ($a, $b) {
            $typeOrder = ['fg' => 1, 'sfg' => 2, 'component' => 3];
            $cmp = ($typeOrder[$a['output_type']] ?? 9) <=> ($typeOrder[$b['output_type']] ?? 9);
            return $cmp !== 0 ? $cmp : strcmp($a['product_name'], $b['product_name']);
        });

        // Format Work Center & Machine Breakdown rows
        $wcBreakdown = [];
        foreach ($wcMachineGroups as $g) {
            $attempted = $g['good_qty'] + $g['rejected_qty'] + $g['scrapped_qty'];
            $yield = $attempted > 0 ? round(($g['good_qty'] / $attempted) * 100, 1) : 100.0;

            $wcBreakdown[] = [
                'work_center_id'   => $g['work_center_id'],
                'work_center_name' => $g['work_center_name'],
                'work_center_code' => $g['work_center_code'],
                'machine_id'       => $g['machine_id'],
                'machine_name'     => $g['machine_name'],
                'machine_code'     => $g['machine_code'],
                'good_qty'         => $g['good_qty'],
                'rejected_qty'     => $g['rejected_qty'],
                'scrapped_qty'     => $g['scrapped_qty'],
                'yield_pct'        => $yield,
                'run_minutes'      => $g['run_minutes'],
                'run_hours'        => round($g['run_minutes'] / 60, 2),
                'setup_minutes'    => $g['setup_minutes'],
                'setup_hours'      => round($g['setup_minutes'] / 60, 2),
                'events_count'     => $g['events_count'],
            ];
        }
        usort($wcBreakdown, function ($a, $b) {
            $cmp = strcmp($a['work_center_name'], $b['work_center_name']);
            return $cmp !== 0 ? $cmp : strcmp($a['machine_name'], $b['machine_name']);
        });

        // Compute overall FG yield & period metrics
        $fgAttempted = $fgProduced + $fgRejected + $fgScrapped;
        $fgYieldPct = $fgAttempted > 0 ? round(($fgProduced / $fgAttempted) * 100, 1) : 100.0;
        $activeDaysCount = count($dailyBreakdown);
        $avgDailyFgOutput = $activeDaysCount > 0 ? round($fgProduced / $activeDaysCount, 1) : 0.0;

        $kpiSummary = [
            'fg_produced'               => $fgProduced,
            'sfg_produced'              => $sfgProduced,
            'component_produced'        => $componentProduced,
            'total_good_units'          => $fgProduced, // Backward compatibility alias for FG Produced
            'total_rejected'            => $totalRejectedAll,
            'total_scrapped'            => $totalScrappedAll,
            'fg_rejected'               => $fgRejected,
            'fg_scrapped'               => $fgScrapped,
            'fg_attempted'              => $fgAttempted,
            'overall_yield_pct'         => $fgYieldPct,
            'fg_yield_pct'              => $fgYieldPct,
            'total_run_minutes'         => $totalRunMinutes,
            'total_run_hours'           => round($totalRunMinutes / 60, 2),
            'total_setup_minutes'       => $totalSetupMinutes,
            'total_setup_hours'         => round($totalSetupMinutes / 60, 2),
            'active_days_count'         => $activeDaysCount,
            'avg_daily_output'          => $avgDailyFgOutput,
            'avg_daily_fg_output'       => $avgDailyFgOutput,
            'active_orders_count'       => count($distinctOrders),
            'active_work_centers_count' => count($distinctWorkCenters),
            'active_machines_count'     => count($distinctMachines),
            'total_events_count'        => count($detailedLogs),
            'total_event_quantity_processed' => $totalEventProcessedQty,
        ];

        return [
            'period_start'          => $start->toDateString(),
            'period_end'            => $end->toDateString(),
            'has_data'              => count($detailedLogs) > 0,
            'summary'               => $kpiSummary,
            'product_outputs'       => $productOutputs,
            'daily_breakdown'       => $dailyBreakdown,
            'work_center_breakdown' => $wcBreakdown,
            'detailed_logs'         => $detailedLogs,
            'data'                  => $detailedLogs, // For generic data access in exports/wrappers
        ];
    }

    /**
     * Resolve the target product for an operation progress event.
     */
    protected function resolveProductForLog(ProductionOrderProgressLog $log): ?Product
    {
        return $log->operation?->sourceProduct ?: $log->order?->product;
    }

    /**
     * Resolve output category: 'fg' (Finished Good), 'sfg' (Semi-Finished), 'component' (Component).
     */
    protected function resolveOutputType(?Product $product, ?ProductionOrderOperation $op, ?ProductionOrder $order): string
    {
        $prodType = $product?->type;

        // 1. Explicit product type classification
        if ($prodType && in_array($prodType, ['component', 'raw_material', 'raw_materials'], true)) {
            return 'component';
        }
        if ($prodType && in_array($prodType, ['semi_finished', 'semi_finished_goods', 'sfg'], true)) {
            return 'sfg';
        }

        // 2. Operation BOM level / intermediate indicator
        if ($op?->is_intermediate) {
            if ($op->bom_level > 2 || $prodType === 'component') {
                return 'component';
            }
            return 'sfg';
        }

        // 3. Top-level finished good (matching parent order or explicitly finished_good)
        if ($order && $product && (int) $product->id === (int) $order->product_id) {
            return 'fg';
        }

        if ($prodType && in_array($prodType, ['finished_good', 'finished_goods', 'fg'], true)) {
            return 'fg';
        }

        return $op?->is_intermediate ? 'sfg' : 'fg';
    }

    /**
     * Determine if an operation is the terminal / completion gate for its product within the order.
     * Non-terminal operations represent in-process stage events (e.g. welding -> finishing -> packaging).
     */
    protected function isTerminalOperationForProduct(ProductionOrderOperation $op, ?ProductionOrder $order): bool
    {
        if (!$order) {
            return true;
        }

        $allOps = $order->operations;
        if (!$allOps || $allOps->isEmpty()) {
            return true;
        }

        $sourceProductId = $op->source_product_id;

        foreach ($allOps as $other) {
            if ((int) $other->id === (int) $op->id) {
                continue;
            }

            // Same product stream match
            $isSameStream = false;
            if ($sourceProductId) {
                $isSameStream = ((int) $other->source_product_id === (int) $sourceProductId);
            } else {
                // Master FG stream: non-intermediate operations where source_product_id is null or matches order product
                $isSameStream = !$other->is_intermediate && ($other->source_product_id === null || (int) $other->source_product_id === (int) $order->product_id);
            }

            if ($isSameStream) {
                // If there's an operation with a higher sequence, or it has $op as predecessor/previous_operation_id
                if ((int) $other->sequence > (int) $op->sequence || (int) $other->previous_operation_id === (int) $op->id) {
                    return false; // $op is followed by $other in the same product stream -> in-process stage!
                }
            }
        }

        return true;
    }
}

