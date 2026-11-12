<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use InvalidArgumentException;

/**
 * RoutingCostService — Cost Calculation Skeleton
 *
 * RT-IMPL-14: sums the figures saved on each operation (no recalculation).
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

        $operationCosts    = [];
        $totalCost         = 0.0;
        $totalLaborCost    = 0.0;
        $totalMachineCost  = 0.0;
        $totalSubcontractCost = 0.0;
        $totalOverheadCost = 0.0;
        $totalSetupCostPerRun = 0.0;

        foreach ($routing->operations as $operation) {
            $cost = $this->calculateOperationCost($operation, $quantity);
            $operationCosts[] = $cost;
            if (!$operation->is_alternative) {
                $totalCost += $cost['total_cost'];
                $totalLaborCost += $cost['labor_cost'] ?? 0.0;
                $totalMachineCost += $cost['machine_cost'] ?? 0.0;
                $totalSubcontractCost += $cost['subcontract_cost'] ?? 0.0;
                $totalOverheadCost += $cost['overhead_cost'] ?? 0.0;
                $totalSetupCostPerRun += $cost['setup_cost_per_run'] ?? 0.0;
            }
        }

        return [
            'routing_id'        => $routing->id,
            'routing_number'    => $routing->routing_number,
            'routing_name'      => $routing->name,
            'quantity'          => $quantity,
            'operations'        => $operationCosts,
            'total_cost'        => round($totalCost, 4),
            'labor_cost'        => round($totalLaborCost, 4),
            'machine_cost'      => round($totalMachineCost, 4),
            'subcontract_cost'  => round($totalSubcontractCost, 4),
            'overhead_cost'     => round($totalOverheadCost, 4),
            'setup_cost_per_run' => round($totalSetupCostPerRun, 4), // RT-IMPL-13: once per production run, shown separately
            'cost_per_unit'     => $quantity > 0 ? round($totalCost / $quantity, 4) : 0.0,
            'total_time_min'    => $routing->totalCycleTimeMinutes(),
            'costs_not_calculated' => collect($operationCosts)->contains(fn($c) => !empty($c['costs_not_calculated'])),
        ];
    }

    /**
     * Cost of a single operation — RT-IMPL-14 (RT-19 rule 5): reads the SAVED figures only
     * (calculated once in the form / API, checked by the backend). No snapshot / live / Work Center lookups.
     * An old operation without saved figures returns 0 with `costs_not_calculated = true`
     * ("Costs not calculated — open and save the routing").
     */
    public function calculateOperationCost(RoutingOperation $operation, float $quantity = 1.0): array
    {
        $yieldFactor = ($operation->expected_yield_percentage > 0) ? (100 / $operation->expected_yield_percentage) : 1.0;
        $notCalculated = $operation->cost_per_unit === null;
        $costPerUnit = (float) ($operation->cost_per_unit ?? 0);

        if ($operation->is_external) {
            $laborPerUnit = 0.0;
            $machinePerUnit = 0.0;
            $subcontractPerUnit = $costPerUnit;
            $resource = 'Vendor #' . ($operation->vendor_id ?? 'N/A');
        } else {
            $laborPerUnit = (float) ($operation->labor_cost_per_unit ?? 0);
            $machinePerUnit = (float) ($operation->machine_cost_per_unit ?? 0);
            // operations saved before the split columns existed: whole cost counted as labor
            if ($operation->labor_cost_per_unit === null && $operation->machine_cost_per_unit === null) {
                $laborPerUnit = $costPerUnit;
            }
            $subcontractPerUnit = 0.0;
            $resource = $operation->machine?->name
                ?? $operation->manualProduction?->batch_name
                ?? '';
        }

        return [
            'operation_id'              => $operation->id,
            'sequence'                  => $operation->sequence,
            'operation_number'          => $operation->operation_number,
            'operation_name'            => $operation->name,
            'operation_type'            => $operation->operation_type,
            'work_center'               => $operation->is_external ? null : $operation->workCenter?->name,
            'section'                   => $operation->is_external ? null : $operation->section?->name,
            'resource'                  => $resource,
            'is_external'               => (bool) $operation->is_external,
            'is_alternative'            => (bool) $operation->is_alternative,
            'replaces_operation_id'     => $operation->replaces_operation_id,
            'priority'                  => $operation->priority,
            'setup_minutes'             => $operation->setup_time_minutes,
            'setup_per_piece_minutes'   => (float) ($operation->setup_time_per_piece_minutes ?? 0),
            'processing_minutes'        => $operation->processing_time_minutes,
            'expected_yield_percentage' => $operation->expected_yield_percentage,
            'yield_factor'              => $yieldFactor,
            'labor_rate_per_minute'     => (float) $operation->labor_cost_rate,
            'machine_rate_per_minute'   => (float) $operation->machine_cost_rate,
            'labor_cost'                => round($laborPerUnit * $quantity, 4),
            'machine_cost'              => round($machinePerUnit * $quantity, 4),
            'subcontract_cost'          => round($subcontractPerUnit * $quantity, 4),
            'setup_cost_per_run'        => $operation->is_external ? 0.0 : $operation->setupCostPerRun(),
            'cost_per_unit'             => round($costPerUnit, 4),
            'total_cost'                => round($costPerUnit * $quantity, 4),
            'section_snapshot_number'   => $operation->section_snapshot_number,
            'costs_not_calculated'      => $notCalculated,
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
