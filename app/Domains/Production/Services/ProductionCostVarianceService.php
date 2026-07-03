<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;

class ProductionCostVarianceService
{
    /**
     * Compute cost analysis comparing Planned vs. Actual manufacturing costs for the order.
     */
    public function getCostAnalysis(ProductionOrder $order): array
    {
        // 1. Material Costs
        $plannedMaterialCost = 0.0;
        foreach ($order->reservations as $res) {
            $plannedMaterialCost += ($res->quantity_planned * (float) ($res->product->unit_cost ?? 0.0));
        }

        $actualMaterialCost = 0.0;
        foreach ($order->issues as $iss) {
            // Negative issues (returns) naturally deduct from the sum
            $actualMaterialCost += ($iss->quantity_issued * (float) ($iss->product->unit_cost ?? 0.0));
        }

        // 2. Routing Operations Costs (Labor, Machine & Work Center Overheads)
        $plannedLaborCost = 0.0;
        $plannedMachineCost = 0.0;
        $plannedOverheadCost = 0.0;

        $actualLaborCost = 0.0;
        $actualMachineCost = 0.0;
        $actualOverheadCost = 0.0;

        foreach ($order->operations as $op) {
            $laborRate = $op->routingOperation ? (float) $op->routingOperation->labor_cost_rate : 0.0;
            $machineRate = $op->routingOperation ? (float) $op->routingOperation->machine_cost_rate : 0.0;
            $overheadRatePerMin = $op->workCenter ? ((float) $op->workCenter->overhead_rate / 60.0) : 0.0;

            // Planned Calculations
            $plannedLaborCost += ($op->total_time_planned * $laborRate);
            $plannedMachineCost += ($op->total_time_planned * $machineRate);
            $plannedOverheadCost += ($op->total_time_planned * $overheadRatePerMin);

            // Actual Calculations
            $actualTime = (float) $op->setup_time_actual + (float) $op->processing_time_actual;
            $actualLaborCost += ($actualTime * $laborRate);
            $actualMachineCost += ($actualTime * $machineRate);
            $actualOverheadCost += ($actualTime * $overheadRatePerMin);
        }

        // 3. Sum totals
        $plannedTotal = $plannedMaterialCost + $plannedLaborCost + $plannedMachineCost + $plannedOverheadCost;
        $actualTotal = $actualMaterialCost + $actualLaborCost + $actualMachineCost + $actualOverheadCost;
        $totalVariance = $actualTotal - $plannedTotal;

        return [
            'material' => [
                'planned'  => $plannedMaterialCost,
                'actual'   => $actualMaterialCost,
                'variance' => $actualMaterialCost - $plannedMaterialCost,
            ],
            'labor' => [
                'planned'  => $plannedLaborCost,
                'actual'   => $actualLaborCost,
                'variance' => $actualLaborCost - $plannedLaborCost,
            ],
            'machine' => [
                'planned'  => $plannedMachineCost,
                'actual'   => $actualMachineCost,
                'variance' => $actualMachineCost - $plannedMachineCost,
            ],
            'overhead' => [
                'planned'  => $plannedOverheadCost,
                'actual'   => $actualOverheadCost,
                'variance' => $actualOverheadCost - $plannedOverheadCost,
            ],
            'totals' => [
                'planned'  => $plannedTotal,
                'actual'   => $actualTotal,
                'variance' => $totalVariance,
                'variance_percentage' => $plannedTotal > 0 ? round(($totalVariance / $plannedTotal) * 100, 1) : 0.0,
            ],
        ];
    }
}
