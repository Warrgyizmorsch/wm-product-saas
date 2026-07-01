<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;

class ProductionCostService
{
    /**
     * Calculate total material cost of a BOM (scaled to its base quantity).
     * Formula: sum(gross_qty * unit_cost)
     */
    public function calculateMaterialCost(ProductionBom $bom): float
    {
        $bom->loadMissing('items.material');

        $totalCost = 0.0;
        foreach ($bom->items as $item) {
            $scrapFactor = 1 + ($item->material_scrap_percentage / 100);
            $grossQty = $item->quantity * $scrapFactor;
            $unitCost = $item->material->unit_cost ?? 0.0;
            $totalCost += $grossQty * $unitCost;
        }

        return $totalCost;
    }

    /**
     * Calculate total routing labor and machine cost of a BOM (scaled to its base quantity).
     * Formula: sum((setup_time + processing_time) * (labor_rate + machine_rate) * base_quantity * yield_factor)
     */
    public function calculateRoutingCost(ProductionBom $bom): float
    {
        if (!$bom->routing_id) {
            return 0.0;
        }

        $bom->loadMissing('routing.operations');
        $routing = $bom->routing;
        if (!$routing) {
            return 0.0;
        }

        $totalCost = 0.0;
        foreach ($routing->operations as $operation) {
            $activeMinutes = $operation->setup_time_minutes + $operation->processing_time_minutes;
            $yieldFactor = ($operation->expected_yield_percentage > 0)
                ? (100 / $operation->expected_yield_percentage)
                : 1.0;

            $laborCost = $activeMinutes * $operation->labor_cost_rate * $bom->base_quantity * $yieldFactor;
            $machineCost = $activeMinutes * $operation->machine_cost_rate * $bom->base_quantity * $yieldFactor;
            $totalCost += $laborCost + $machineCost;
        }

        return $totalCost;
    }

    /**
     * Calculate total manufacturing cost (Material Cost + Routing labor/machine overheads).
     */
    public function calculateTotalManufacturingCost(ProductionBom $bom): float
    {
        return $this->calculateMaterialCost($bom) + $this->calculateRoutingCost($bom);
    }

    /**
     * Calculate cost summary details.
     */
    public function calculateCost(ProductionBom $bom): array
    {
        return [
            'material_cost' => $this->calculateMaterialCost($bom),
            'routing_cost'  => $this->calculateRoutingCost($bom),
            'total_cost'    => $this->calculateTotalManufacturingCost($bom),
        ];
    }
}
