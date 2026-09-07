<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProductionVarianceAnalysisService
{
    public const TIME_ON_TARGET = 'ON_TARGET';
    public const TIME_MINOR_VARIANCE = 'MINOR_VARIANCE';
    public const TIME_SIGNIFICANT_VARIANCE = 'SIGNIFICANT_VARIANCE';

    public const QTY_ON_TARGET = 'ON_TARGET';
    public const QTY_OVER_PRODUCTION = 'OVER_PRODUCTION';
    public const QTY_UNDER_PRODUCTION = 'UNDER_PRODUCTION';

    public const EXEC_NORMAL = 'NORMAL';
    public const EXEC_SCRAP = 'SCRAP';
    public const EXEC_REWORK = 'REWORK';
    public const EXEC_REJECTION = 'REJECTION';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';

    // Tolerances
    public const TIME_MINOR_THRESHOLD_PCT = 5.0;
    public const TIME_SIGNIFICANT_THRESHOLD_PCT = 15.0;
    public const QTY_TOLERANCE_PCT = 1.0;

    /**
     * Analyze variance for a single Production Order using its planning snapshots.
     */
    public function analyzeProductionOrder(ProductionOrder|int $order): array
    {
        if (is_int($order)) {
            $order = ProductionOrder::withoutGlobalScopes()
                ->with(['operations.workCenter', 'operations.routingOperation', 'operations.reworks', 'product', 'bom'])
                ->findOrFail($order);
        } else {
            $order->loadMissing(['operations.workCenter', 'operations.routingOperation', 'operations.reworks', 'product', 'bom']);
        }

        $operations = $order->operations->sortBy('sequence');

        $opAnalyses = [];
        $plannedTotalTime = 0.0;
        $actualTotalTime = 0.0;
        $totalScrap = 0.0;
        $totalRejected = 0.0;
        $totalRework = 0.0;

        foreach ($operations as $op) {
            $analysis = $this->analyzeOperation($op);
            $opAnalyses[] = $analysis;

            $plannedTotalTime += $analysis['planned_total_time'];
            $actualTotalTime += $analysis['actual_total_time'];
            $totalScrap += $analysis['scrap_quantity'];
            $totalRejected += $analysis['rejected_quantity'];
            $totalRework += $analysis['rework_quantity'];
        }

        $totalTimeVariance = round($actualTotalTime - $plannedTotalTime, 2);
        $timeVariancePct = $plannedTotalTime > 0 ? round(($totalTimeVariance / $plannedTotalTime) * 100, 2) : 0.0;

        $timeClassification = match (true) {
            abs($timeVariancePct) <= self::TIME_MINOR_THRESHOLD_PCT => self::TIME_ON_TARGET,
            abs($timeVariancePct) <= self::TIME_SIGNIFICANT_THRESHOLD_PCT => self::TIME_MINOR_VARIANCE,
            default => self::TIME_SIGNIFICANT_VARIANCE,
        };

        $plannedQty = (float) $order->quantity_ordered;
        $actualQty = (float) $order->quantity_produced;
        $qtyVariance = round($actualQty - $plannedQty, 4);
        $yieldPct = $plannedQty > 0 ? round(($actualQty / $plannedQty) * 100, 2) : 0.0;

        $isCompleted = in_array($order->status, [ProductionOrder::STATUS_COMPLETED, ProductionOrder::STATUS_CLOSED]);
        
        $qtyClassification = self::STATUS_IN_PROGRESS;
        if ($isCompleted) {
            $qtyVariancePct = $plannedQty > 0 ? ($qtyVariance / $plannedQty) * 100 : 0.0;
            if (abs($qtyVariancePct) <= self::QTY_TOLERANCE_PCT) {
                $qtyClassification = self::QTY_ON_TARGET;
            } elseif ($qtyVariancePct > self::QTY_TOLERANCE_PCT) {
                $qtyClassification = self::QTY_OVER_PRODUCTION;
            } else {
                $qtyClassification = self::QTY_UNDER_PRODUCTION;
            }
        }

        $execClassification = match (true) {
            $totalRejected > 0 => self::EXEC_REJECTION,
            $totalScrap > 0 => self::EXEC_SCRAP,
            $totalRework > 0 => self::EXEC_REWORK,
            default => self::EXEC_NORMAL,
        };

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'tenant_id' => $order->tenant_id,
            'status' => $order->status,
            'is_completed' => $isCompleted,
            'product_id' => $order->product_id,
            'product_name' => $order->product?->name ?? 'Product #' . $order->product_id,
            'bom_id' => $order->bom_id,
            'routing_id' => $order->routing_id,

            // Quantity metrics
            'planned_quantity' => $plannedQty,
            'actual_completed_quantity' => $actualQty,
            'actual_quantity' => $actualQty,
            'quantity_variance' => $qtyVariance,
            'yield_percentage' => $yieldPct,
            'scrap_quantity' => round($totalScrap + (float) $order->quantity_scrapped, 4),
            'rework_quantity' => round($totalRework, 4),
            'rejected_quantity' => round($totalRejected + (float) $order->quantity_rejected, 4),

            // Time metrics
            'planned_total_time' => round($plannedTotalTime, 2),
            'actual_total_time' => round($actualTotalTime, 2),
            'total_planned_runtime_minutes' => round($plannedTotalTime, 2),
            'total_actual_runtime_minutes' => round($actualTotalTime, 2),
            'total_time_variance' => $totalTimeVariance,
            'time_variance_percentage' => $timeVariancePct,

            // Classifications
            'time_classification' => $timeClassification,
            'quantity_classification' => $qtyClassification,
            'execution_classification' => $execClassification,

            // Operations breakdown
            'operations' => $opAnalyses,
            'operation_variances' => $opAnalyses,
        ];
    }

    /**
     * Analyze variance for a single operation.
     */
    public function analyzeOperation(ProductionOrderOperation $op): array
    {
        $plannedSetup = (float) $op->setup_time_planned;
        $actualSetup = (float) $op->setup_time_actual;
        $setupVariance = round($actualSetup - $plannedSetup, 2);

        $plannedRun = (float) $op->processing_time_planned;
        $actualRun = (float) $op->processing_time_actual;
        $runVariance = round($actualRun - $plannedRun, 2);

        $plannedTotal = (float) ($op->total_time_planned > 0 ? $op->total_time_planned : ($plannedSetup + $plannedRun));

        // If actual setup/run explicitly recorded, use sum; else diff actual start/end timestamps
        $actualTotal = $actualSetup + $actualRun;
        if ($actualTotal <= 0 && $op->actual_start_time && $op->actual_end_time) {
            $actualTotal = (float) $op->actual_start_time->diffInMinutes($op->actual_end_time);
        }

        $totalTimeVariance = round($actualTotal - $plannedTotal, 2);
        $timeVariancePct = $plannedTotal > 0 ? round(($totalTimeVariance / $plannedTotal) * 100, 2) : 0.0;

        $timeClassification = match (true) {
            abs($timeVariancePct) <= self::TIME_MINOR_THRESHOLD_PCT => self::TIME_ON_TARGET,
            abs($timeVariancePct) <= self::TIME_SIGNIFICANT_THRESHOLD_PCT => self::TIME_MINOR_VARIANCE,
            default => self::TIME_SIGNIFICANT_VARIANCE,
        };

        $plannedQty = (float) $op->target_produced_qty;
        $actualQty = (float) $op->quantity_produced;
        $qtyVariance = round($actualQty - $plannedQty, 4);

        $scrapQty = (float) $op->quantity_scrapped;
        $rejectedQty = (float) $op->quantity_rejected;

        $explicitRework = ($op->relationLoaded('reworks') && $op->reworks->count() > 0)
            ? (float) $op->reworks->sum('quantity')
            : null;

        $reworkQty = $explicitRework !== null
            ? $explicitRework
            : max(0.0, (float) $op->quantity_claimed - $actualQty);

        $execClassification = match (true) {
            $rejectedQty > 0 => self::EXEC_REJECTION,
            $scrapQty > 0 => self::EXEC_SCRAP,
            $reworkQty > 0 => self::EXEC_REWORK,
            default => self::EXEC_NORMAL,
        };

        $machineUsedMatch = true;
        if ($op->machine_id && $op->machine_used_id) {
            $machineUsedMatch = ((int)$op->machine_id === (int)$op->machine_used_id);
        }

        return [
            'operation_id' => $op->id,
            'operation_number' => $op->operation_number,
            'name' => $op->name,
            'operation_name' => $op->name,
            'sequence' => $op->sequence,
            'status' => $op->status,
            'is_external' => (bool) $op->is_external,
            'work_center_id' => $op->work_center_id,
            'planned_machine_id' => $op->machine_id,
            'actual_machine_id' => $op->machine_used_id,
            'machine_match' => $machineUsedMatch,

            // Setup time
            'planned_setup_time' => $plannedSetup,
            'actual_setup_time' => $actualSetup,
            'setup_time_variance' => $setupVariance,

            // Run time
            'planned_run_time' => $plannedRun,
            'actual_run_time' => $actualRun,
            'run_time_variance' => $runVariance,

            // Total time
            'planned_total_time' => $plannedTotal,
            'actual_total_time' => round($actualTotal, 2),
            'planned_time_minutes' => $plannedTotal,
            'actual_time_minutes' => round($actualTotal, 2),
            'total_time_variance' => $totalTimeVariance,
            'time_variance_minutes' => $totalTimeVariance,
            'time_variance_percentage' => $timeVariancePct,

            // Quantity
            'planned_quantity' => $plannedQty,
            'actual_quantity' => $actualQty,
            'quantity_variance' => $qtyVariance,
            'scrap_quantity' => $scrapQty,
            'scrap_qty' => $scrapQty,
            'rejected_quantity' => $rejectedQty,
            'rejected_qty' => $rejectedQty,
            'rework_quantity' => $reworkQty,
            'rework_qty' => $reworkQty,

            // Classifications
            'time_classification' => $timeClassification,
            'execution_classification' => $execClassification,
        ];
    }

    /**
     * Analyze routing operation variances for an order.
     */
    public function analyzeRouting(ProductionOrder|int $order): array
    {
        $analysis = $this->analyzeProductionOrder($order);
        return [
            'order_id' => $analysis['order_id'],
            'product_id' => $analysis['product_id'],
            'routing_id' => $analysis['routing_id'],
            'time_classification' => $analysis['time_classification'],
            'total_time_variance' => $analysis['total_time_variance'],
            'operations' => $analysis['operations'],
        ];
    }

    /**
     * Aggregate recurring routing variances across completed or active orders for a tenant.
     */
    public function analyzeRecurringRoutingVariances(int $tenantId, int $limit = 10): array
    {
        $orders = ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [ProductionOrder::STATUS_COMPLETED, ProductionOrder::STATUS_CLOSED, ProductionOrder::STATUS_IN_PROGRESS])
            ->with(['operations.workCenter', 'operations.reworks', 'product', 'reworks'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $operationGroups = [];

        foreach ($orders as $order) {
            $analysis = $this->analyzeProductionOrder($order);
            foreach ($analysis['operations'] as $op) {
                $key = "p{$analysis['product_id']}_op{$op['name']}";
                if (!isset($operationGroups[$key])) {
                    $operationGroups[$key] = [
                        'product_id' => $analysis['product_id'],
                        'product_name' => $analysis['product_name'],
                        'routing_id' => $analysis['routing_id'],
                        'operation_name' => $op['name'],
                        'routing_operation_id' => $op['operation_id'],
                        'sample_count' => 0,
                        'total_time_variance' => 0.0,
                        'total_planned_time' => 0.0,
                        'total_actual_time' => 0.0,
                        'scrap_occurrences' => 0,
                        'alternate_machine_occurrences' => 0,
                        'significant_variance_count' => 0,
                        'order_ids' => [],
                    ];
                }

                $operationGroups[$key]['sample_count']++;
                $operationGroups[$key]['total_time_variance'] += $op['total_time_variance'];
                $operationGroups[$key]['total_planned_time'] += $op['planned_total_time'];
                $operationGroups[$key]['total_actual_time'] += $op['actual_total_time'];
                if ($op['scrap_quantity'] > 0) $operationGroups[$key]['scrap_occurrences']++;
                if (!$op['machine_match']) $operationGroups[$key]['alternate_machine_occurrences']++;
                if ($op['time_classification'] === self::TIME_SIGNIFICANT_VARIANCE) $operationGroups[$key]['significant_variance_count']++;
                $operationGroups[$key]['order_ids'][] = $order->id;
            }
        }

        $results = [];
        foreach ($operationGroups as $group) {
            if ($group['sample_count'] === 0) continue;

            $avgPlanned = $group['total_planned_time'] / $group['sample_count'];
            $avgActual = $group['total_actual_time'] / $group['sample_count'];
            $avgVariance = $group['total_time_variance'] / $group['sample_count'];
            $avgVariancePct = $avgPlanned > 0 ? round(($avgVariance / $avgPlanned) * 100, 2) : 0.0;

            $results[] = array_merge($group, [
                'average_planned_time' => round($avgPlanned, 2),
                'average_actual_time' => round($avgActual, 2),
                'average_time_variance' => round($avgVariance, 2),
                'average_variance_percentage' => $avgVariancePct,
            ]);
        }

        // Sort by highest average variance percentage descending
        usort($results, fn($a, $b) => abs($b['average_variance_percentage']) <=> abs($a['average_variance_percentage']));

        return array_slice($results, 0, $limit);
    }
}
