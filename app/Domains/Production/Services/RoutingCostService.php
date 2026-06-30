<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use InvalidArgumentException;

/**
 * RoutingCostService — Cost Calculation Skeleton
 *
 * Current: calculates per-operation cost breakdown from rate fields.
 * Future: combine with BomExplosionService for total manufacturing cost.
 *
 * Formula per operation:
 *   Labor Cost  = (setup_time + processing_time) * labor_cost_rate
 *   Machine Cost= (setup_time + processing_time) * machine_cost_rate
 *   Total Op    = Labor Cost + Machine Cost
 *   Yield Adj.  = Total Op / (expected_yield_percentage / 100)
 */
class RoutingCostService
{
    public function __construct(
        private readonly RoutingRepositoryInterface $routingRepository
    ) {}

    /**
     * Calculate routing cost breakdown per operation.
     * Returns array with per-operation costs and routing total.
     */
    public function calculateRoutingCost(int $routingId, float $quantity = 1.0): array
    {
        $routing = $this->routingRepository->getRoutingWithOperations($routingId);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        $operationCosts = [];
        $totalCost      = 0.0;

        foreach ($routing->operations as $operation) {
            $cost = $this->calculateOperationCost($operation, $quantity);
            $operationCosts[] = $cost;
            $totalCost += $cost['total_cost'];
        }

        return [
            'routing_id'      => $routing->id,
            'routing_number'  => $routing->routing_number,
            'routing_name'    => $routing->name,
            'quantity'        => $quantity,
            'operations'      => $operationCosts,
            'total_cost'      => $totalCost,
            'cost_per_unit'   => $quantity > 0 ? $totalCost / $quantity : 0.0,
            'total_time_min'  => $routing->totalCycleTimeMinutes(),
        ];
    }

    /**
     * Calculate cost for a single operation.
     * A2: Applies yield adjustment — actual input cost is higher due to losses.
     */
    public function calculateOperationCost(RoutingOperation $operation, float $quantity = 1.0): array
    {
        $activeMinutes  = $operation->setup_time_minutes + $operation->processing_time_minutes;
        $yieldFactor    = ($operation->expected_yield_percentage > 0)
                          ? (100 / $operation->expected_yield_percentage)
                          : 1.0;

        $laborCost      = $activeMinutes * $operation->labor_cost_rate * $quantity * $yieldFactor;
        $machineCost    = $activeMinutes * $operation->machine_cost_rate * $quantity * $yieldFactor;
        $totalCost      = $laborCost + $machineCost;

        return [
            'operation_id'              => $operation->id,
            'sequence'                  => $operation->sequence,
            'operation_name'            => $operation->name,
            'operation_type'            => $operation->operation_type,
            'work_center'               => $operation->workCenter?->name,
            'setup_minutes'             => $operation->setup_time_minutes,
            'processing_minutes'        => $operation->processing_time_minutes,
            'expected_yield_percentage' => $operation->expected_yield_percentage,
            'yield_factor'              => $yieldFactor,
            'labor_cost'                => round($laborCost, 4),
            'machine_cost'              => round($machineCost, 4),
            'total_cost'                => round($totalCost, 4),
        ];
    }

    /**
     * Future method — combines BOM material cost + routing operation cost.
     * Stub signature preserved for ManufacturingCostService integration.
     * Do NOT implement until Costing module is built.
     *
     * @throws \RuntimeException always — not yet implemented
     */
    public function calculateManufacturingCost(int $productId, float $quantity = 1.0): array
    {
        throw new \RuntimeException(
            'calculateManufacturingCost() is not yet implemented. ' .
            'Pending: Costing module + BomExplosionService integration.'
        );
    }
}
