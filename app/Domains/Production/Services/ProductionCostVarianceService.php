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

    /**
     * Compute day-wise production output and costing history for the order.
     */
    public function getDailyCostHistory(ProductionOrder $order, array $dailyManualAdjustments = []): array
    {
        // 1. Group material issues by date Y-m-d
        $dailyMaterialCost = [];
        foreach ($order->issues as $iss) {
            $date = $iss->issued_at ? $iss->issued_at->format('Y-m-d') : ($iss->created_at ? $iss->created_at->format('Y-m-d') : now()->format('Y-m-d'));
            $cost = (float) $iss->quantity_issued * (float) ($iss->product->unit_cost ?? 0.0);
            $dailyMaterialCost[$date] = ($dailyMaterialCost[$date] ?? 0.0) + $cost;
        }

        // 2. Group progress logs by date Y-m-d
        $logsByDate = [];
        foreach ($order->progressLogs as $log) {
            $date = $log->recorded_at ? $log->recorded_at->format('Y-m-d') : ($log->created_at ? $log->created_at->format('Y-m-d') : now()->format('Y-m-d'));
            $logsByDate[$date][] = $log;
        }

        // 3. Merge all dates including manual adjustment dates
        $allDates = array_unique(array_merge(
            array_keys($dailyMaterialCost),
            array_keys($logsByDate),
            array_keys($dailyManualAdjustments)
        ));
        sort($allDates); // Ascending for cumulative calculation

        $history = [];
        $cumulativeQty = 0.0;
        $cumulativeAutoCost = 0.0;
        $cumulativeManualAdjustment = 0.0;
        $cumulativeFinalCost = 0.0;

        foreach ($allDates as $date) {
            $dayLogs = $logsByDate[$date] ?? [];
            $matCost = $dailyMaterialCost[$date] ?? 0.0;

            $producedQty = 0.0;
            $rejectedQty = 0.0;
            $scrappedQty = 0.0;
            $setupMinutes = 0.0;
            $runMinutes = 0.0;
            $laborCost = 0.0;
            $machineCost = 0.0;
            $overheadCost = 0.0;
            $operators = [];
            $machines = [];
            $operationsWorked = [];

            foreach ($dayLogs as $log) {
                $producedQty += (float) $log->quantity_produced;
                $rejectedQty += (float) $log->quantity_rejected;
                $scrappedQty += (float) $log->quantity_scrapped;

                $setup = (float) $log->setup_minutes_logged;
                $run = (float) $log->run_minutes_logged;
                $setupMinutes += $setup;
                $runMinutes += $run;
                $actualTime = $setup + $run;

                $op = $log->operation;
                if ($op) {
                    $operationsWorked[$op->operation_number] = $op->name;
                    $laborRate = $op->routingOperation ? (float) $op->routingOperation->labor_cost_rate : 0.0;
                    $machineRate = $op->routingOperation ? (float) $op->routingOperation->machine_cost_rate : 0.0;
                    $overheadRatePerMin = $op->workCenter ? ((float) $op->workCenter->overhead_rate / 60.0) : 0.0;

                    $laborCost += ($actualTime * $laborRate);
                    $machineCost += ($actualTime * $machineRate);
                    $overheadCost += ($actualTime * $overheadRatePerMin);
                }

                if ($log->user && !in_array($log->user->name, $operators)) {
                    $operators[] = $log->user->name;
                }

                if ($log->machine && !in_array($log->machine->name, $machines)) {
                    $machines[] = $log->machine->name;
                }
            }

            $totalDailyMinutes = $setupMinutes + $runMinutes;
            $autoDailyCost     = $matCost + $laborCost + $machineCost + $overheadCost;
            $manualAdjustment  = (float) ($dailyManualAdjustments[$date] ?? 0.0);
            $finalDailyCost    = $autoDailyCost + $manualAdjustment;

            $cumulativeQty                += $producedQty;
            $cumulativeAutoCost           += $autoDailyCost;
            $cumulativeManualAdjustment   += $manualAdjustment;
            $cumulativeFinalCost          += $finalDailyCost;

            $history[] = [
                'date'                          => $date,
                'operations_worked'             => implode(', ', array_keys($operationsWorked)),
                'quantity_produced'             => $producedQty,
                'quantity_rejected'             => $rejectedQty,
                'quantity_scrapped'             => $scrappedQty,
                'setup_minutes'                 => $setupMinutes,
                'run_minutes'                   => $runMinutes,
                'total_minutes'                 => $totalDailyMinutes,
                'operators'                     => implode(', ', $operators),
                'machines'                      => implode(', ', $machines),
                'material_cost'                 => $matCost,
                'labor_cost'                    => $laborCost,
                'machine_cost'                  => $machineCost,
                'overhead_cost'                 => $overheadCost,
                'total_daily_cost'              => $autoDailyCost,
                'automatic_daily_cost'          => $autoDailyCost,
                'manual_daily_adjustment'       => $manualAdjustment,
                'final_daily_cost'              => $finalDailyCost,
                'cumulative_qty'                => $cumulativeQty,
                'cumulative_cost'               => $cumulativeAutoCost,
                'cumulative_automatic_cost'     => $cumulativeAutoCost,
                'cumulative_manual_adjustment'  => $cumulativeManualAdjustment,
                'cumulative_final_cost'         => $cumulativeFinalCost,
            ];
        }

        // Return newest date first
        usort($history, fn($a, $b) => strcmp($b['date'], $a['date']));

        return $history;
    }
}
