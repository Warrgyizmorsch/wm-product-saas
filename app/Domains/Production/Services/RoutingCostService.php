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

        $operationCosts    = [];
        $totalCost         = 0.0;
        $totalLaborCost    = 0.0;
        $totalMachineCost  = 0.0;
        $totalOverheadCost = 0.0;

        foreach ($routing->operations as $operation) {
            $cost = $this->calculateOperationCost($operation, $quantity);
            $operationCosts[] = $cost;
            if (!$operation->is_alternative) {
                $totalCost += $cost['total_cost'];
                $totalLaborCost += $cost['labor_cost'] ?? 0.0;
                $totalMachineCost += $cost['machine_cost'] ?? 0.0;
                $totalOverheadCost += $cost['overhead_cost'] ?? 0.0;
            }
        }

        return [
            'routing_id'      => $routing->id,
            'routing_number'  => $routing->routing_number,
            'routing_name'    => $routing->name,
            'quantity'        => $quantity,
            'operations'      => $operationCosts,
            'total_cost'      => round($totalCost, 4),
            'labor_cost'      => round($totalLaborCost, 4),
            'machine_cost'    => round($totalMachineCost, 4),
            'overhead_cost'   => round($totalOverheadCost, 4),
            'cost_per_unit'   => $quantity > 0 ? round($totalCost / $quantity, 4) : 0.0,
            'total_time_min'  => $routing->totalCycleTimeMinutes(),
        ];
    }

    /**
     * Calculate cost for a single operation.
     * Applies yield adjustment — actual input cost is higher due to losses.
     */
    public function calculateOperationCost(RoutingOperation $operation, float $quantity = 1.0): array
    {
        $activeMinutes  = $operation->setup_time_minutes + $operation->processing_time_minutes;
        $yieldFactor    = ($operation->expected_yield_percentage > 0)
                          ? (100 / $operation->expected_yield_percentage)
                          : 1.0;

        if ($operation->is_external) {
            $subcontractCostPerUnit = (float) $operation->subcontract_cost_per_unit;
            $unitCost = $subcontractCostPerUnit * $yieldFactor;
            $totalCost = $unitCost * $quantity;

            return [
                'operation_id'              => $operation->id,
                'sequence'                  => $operation->sequence,
                'operation_number'          => $operation->operation_number,
                'operation_name'            => $operation->name,
                'operation_type'            => $operation->operation_type,
                'work_center'               => null,
                'section'                   => null,
                'resource'                  => 'Vendor #' . ($operation->vendor_id ?? 'N/A'),
                'is_external'               => true,
                'is_alternative'            => (bool) $operation->is_alternative,
                'replaces_operation_id'     => $operation->replaces_operation_id,
                'priority'                  => $operation->priority,
                'setup_minutes'             => $operation->setup_time_minutes,
                'processing_minutes'        => $operation->processing_time_minutes,
                'expected_yield_percentage' => $operation->expected_yield_percentage,
                'yield_factor'              => $yieldFactor,
                'labor_rate_per_minute'     => 0.0,
                'machine_rate_per_minute'   => 0.0,
                'labor_cost'                => 0.0,
                'machine_cost'              => 0.0,
                'subcontract_cost'          => round($totalCost, 4),
                'cost_per_unit'             => round($unitCost, 4),
                'total_cost'                => round($totalCost, 4),
            ];
        }

        $laborRate = (float) $operation->labor_cost_rate;
        $machineRate = (float) $operation->machine_cost_rate;
        $resourceName = '';

        if ($operation->section_manual_production_id) {
            $machineRate = 0.0;
            $batch = $operation->manualProduction;
            $resourceName = $batch ? "Manual: {$batch->batch_name}" : 'Manual Batch';
            if ($laborRate <= 0.0 && $operation->section) {
                $sectionCosts = app(\App\Domains\Production\Services\SectionCalculationService::class)->getSectionResourceCosts($operation->section);
                foreach ($sectionCosts['manual_batches'] as $mb) {
                    if ((int) $mb['id'] === (int) $operation->section_manual_production_id) {
                        $laborRate = ($mb['labor_cost_per_hour'] ?? 0.0) / 60.0;
                        break;
                    }
                }
            }
        } elseif ($operation->machine_id) {
            $machine = $operation->machine;
            $resourceName = $machine ? "Machine: {$machine->name} ({$machine->code})" : 'Machine';
            if ($machineRate <= 0.0 && $machine) {
                $machineRate = ((float) $machine->operating_cost_per_hour) / 60.0;
            }
            if ($laborRate <= 0.0 && $operation->section) {
                $sectionCosts = app(\App\Domains\Production\Services\SectionCalculationService::class)->getSectionResourceCosts($operation->section);
                $laborRate = ($sectionCosts['operator_labor_cost_per_machine_per_hour'] ?? 0.0) / 60.0;
            }
        } else {
            $wcLaborRate = $operation->workCenter ? ((float) $operation->workCenter->cost_per_hour / 60.0) : 0.0;
            if ($laborRate <= 0.0) {
                $laborRate = $wcLaborRate;
            }
        }

        $laborCost   = $activeMinutes * $laborRate * $quantity * $yieldFactor;
        $machineCost = $activeMinutes * $machineRate * $quantity * $yieldFactor;
        $totalCost   = $laborCost + $machineCost;
        $costPerUnit = $quantity > 0 ? ($totalCost / $quantity) : 0.0;

        return [
            'operation_id'              => $operation->id,
            'sequence'                  => $operation->sequence,
            'operation_number'          => $operation->operation_number,
            'operation_name'            => $operation->name,
            'operation_type'            => $operation->operation_type,
            'work_center'               => $operation->workCenter?->name,
            'section'                   => $operation->section?->name,
            'resource'                  => $resourceName,
            'is_external'               => false,
            'is_alternative'            => (bool) $operation->is_alternative,
            'replaces_operation_id'     => $operation->replaces_operation_id,
            'priority'                  => $operation->priority,
            'setup_minutes'             => $operation->setup_time_minutes,
            'processing_minutes'        => $operation->processing_time_minutes,
            'expected_yield_percentage' => $operation->expected_yield_percentage,
            'yield_factor'              => $yieldFactor,
            'labor_rate_per_minute'     => round($laborRate, 4),
            'machine_rate_per_minute'   => round($machineRate, 4),
            'labor_cost'                => round($laborCost, 4),
            'machine_cost'              => round($machineCost, 4),
            'subcontract_cost'          => 0.0,
            'cost_per_unit'             => round($costPerUnit, 4),
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
