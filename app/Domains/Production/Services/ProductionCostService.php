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
            $unitCost = $item->material?->unit_cost ?? 0.0;
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
            $unitCost = $item->material?->unit_cost ?? 0.0;
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
     * Calculate subcontract cost for a ProductionOrder using authoritative cost hierarchy:
     * Actual (Vendor Bill) -> Committed (Approved PO) -> Estimated (Routing Snapshot).
     */
    public function calculateSubcontractCost(\App\Domains\Production\Models\ProductionOrder $order): array
    {
        $tenantId = $order->tenant_id;
        $externalOps = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->where('is_external', true)
            ->get();

        $estimatedCost = 0.0;
        $committedCost = 0.0;
        $actualCost = 0.0;

        foreach ($externalOps as $op) {
            $opTarget = (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : ($order->quantity_ordered ?? 1.0));
            $opEstimated = (float) ($op->subcontract_cost_per_unit * $opTarget);
            $estimatedCost += $opEstimated;

            $poItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('purchase_order_id', function ($q) use ($tenantId, $order) {
                $q->select('id')->from('purchase_orders')
                    ->where('tenant_id', $tenantId)
                    ->where('production_order_id', $order->id);
            })->where('production_order_operation_id', $op->id)->first();

            if ($poItem) {
                $opCommitted = (float) ($poItem->total_amount ?? ($poItem->quantity * $poItem->rate));
                $committedCost += $opCommitted;

                $vendorBillItem = \Illuminate\Support\Facades\DB::table('vendor_bill_items')
                    ->join('vendor_bills', 'vendor_bill_items.vendor_bill_id', '=', 'vendor_bills.id')
                    ->where('vendor_bills.tenant_id', $tenantId)
                    ->where('vendor_bills.purchase_order_id', $poItem->purchase_order_id)
                    ->where('vendor_bill_items.product_id', $poItem->product_id)
                    ->select('vendor_bill_items.*')
                    ->first();

                if ($vendorBillItem && isset($vendorBillItem->total_amount)) {
                    $actualCost += (float) $vendorBillItem->total_amount;
                } else {
                    $actualCost += $opCommitted;
                }
            } else {
                $committedCost += $opEstimated;
                $actualCost += $opEstimated;
            }
        }

        // Authoritative cost selection: alternatives by lifecycle stage, NOT additive!
        $authoritativeCost = match (true) {
            $actualCost > 0.0 => $actualCost,
            $committedCost > 0.0 => $committedCost,
            default => $estimatedCost,
        };

        return [
            'estimated' => $estimatedCost,
            'committed' => $committedCost,
            'actual' => $actualCost,
            'authoritative' => $authoritativeCost,
        ];
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
