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
     * Calculate total routing labor cost of a BOM (scaled to its base quantity).
     * Formula: sum(duration * labor_cost_rate * base_quantity * yield_factor)
     */
    public function calculateLaborCost(ProductionBom $bom): float
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
            $totalCost += $laborCost;
        }

        return $totalCost;
    }

    /**
     * Calculate total routing machine cost of a BOM (scaled to its base quantity).
     * Formula: sum(duration * machine_cost_rate * base_quantity * yield_factor)
     */
    public function calculateMachineCost(ProductionBom $bom): float
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

            $machineCost = $activeMinutes * $operation->machine_cost_rate * $bom->base_quantity * $yieldFactor;
            $totalCost += $machineCost;
        }

        return $totalCost;
    }

    /**
     * Calculate total overhead cost of a BOM (scaled to its base quantity).
     * Formula: sum(duration * (work_center.overhead_rate / 60.0) * base_quantity * yield_factor)
     */
    public function calculateOverheadCost(ProductionBom $bom): float
    {
        if (!$bom->routing_id) {
            return 0.0;
        }

        $bom->loadMissing('routing.operations.workCenter');
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

            $overheadRate = $operation->workCenter ? (float) $operation->workCenter->overhead_rate : 0.0;
            // overhead_rate is hourly, convert to per minute
            $overheadCost = $activeMinutes * ($overheadRate / 60.0) * $bom->base_quantity * $yieldFactor;
            $totalCost += $overheadCost;
        }

        return $totalCost;
    }

    /**
     * Calculate cost of material scrap loss.
     * Formula: sum(quantity * (scrap_pct/100) * unit_cost)
     */
    public function calculateScrapAdjustment(ProductionBom $bom): float
    {
        $bom->loadMissing('items.material');

        $scrapCost = 0.0;
        foreach ($bom->items as $item) {
            $scrapPct = $item->material_scrap_percentage ?? 0.0;
            $scrapQty = $item->quantity * ($scrapPct / 100);
            $unitCost = $item->material->unit_cost ?? 0.0;
            $scrapCost += $scrapQty * $unitCost;
        }

        return $scrapCost;
    }

    /**
     * Calculate total routing labor and machine cost of a BOM (scaled to its base quantity).
     * Formula: sum((setup_time + processing_time) * (labor_rate + machine_rate) * base_quantity * yield_factor)
     */
    public function calculateRoutingCost(ProductionBom $bom): float
    {
        return $this->calculateLaborCost($bom) + $this->calculateMachineCost($bom);
    }

    /**
     * Calculate total manufacturing cost (Material Cost + Labor Cost + Machine Cost + Overhead Cost + Scrap Adjustment).
     */
    public function calculateTotalManufacturingCost(ProductionBom $bom): float
    {
        return $this->calculateMaterialCost($bom) 
             + $this->calculateRoutingCost($bom) 
             + $this->calculateOverheadCost($bom);
    }

    /**
     * Calculate cost summary details.
     */
    public function calculateCost(ProductionBom $bom): array
    {
        return [
            'material_cost'    => $this->calculateMaterialCost($bom),
            'labor_cost'       => $this->calculateLaborCost($bom),
            'machine_cost'     => $this->calculateMachineCost($bom),
            'overhead_cost'    => $this->calculateOverheadCost($bom),
            'scrap_adjustment' => $this->calculateScrapAdjustment($bom),
            'routing_cost'     => $this->calculateRoutingCost($bom),
            'total_cost'       => $this->calculateTotalManufacturingCost($bom),
        ];
    }
}
