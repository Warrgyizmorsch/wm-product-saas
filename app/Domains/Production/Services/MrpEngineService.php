<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPlanRequirement;
use App\Domains\Production\Models\ProductionPlanOperation;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Inventory\Models\Product;
use Illuminate\Support\Facades\DB;

class MrpEngineService
{
    /**
     * Run MRP for a production plan.
     * Generates a snapshot of required materials and routing operations.
     */
    public function runMrp(ProductionPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            // 1. Clear existing plan snapshots
            $plan->requirements()->delete();
            $plan->operations()->delete();

            // 2. Explode BOM Requirements recursively
            if ($plan->bom_id) {
                $this->explodeBom(
                    $plan,
                    $plan->product_id,
                    $plan->bom_id,
                    $plan->quantity,
                    1,
                    null
                );
            }

            // 3. Snapshot Routing Operations capacity load
            if ($plan->routing_id) {
                $routing = Routing::withoutGlobalScopes()->with('operations')->find($plan->routing_id);
                if ($routing) {
                    foreach ($routing->operations as $op) {
                        $totalMinutes = $op->setup_time_minutes + ($op->processing_time_minutes * $plan->quantity);
                        
                        // Scale costs per minute from parent rates
                        ProductionPlanOperation::create([
                            'tenant_id' => $plan->tenant_id,
                            'production_plan_id' => $plan->id,
                            'routing_operation_id' => $op->id,
                            'sequence' => $op->sequence,
                            'operation_number' => $op->operation_number,
                            'name' => $op->name,
                            'work_center_id' => $op->work_center_id,
                            'machine_id' => $op->machine_id,
                            'setup_time_minutes' => $op->setup_time_minutes,
                            'processing_time_minutes' => $op->processing_time_minutes,
                            'total_time_minutes' => $totalMinutes,
                        ]);
                    }
                }
            }

            // 4. Transition status to MRP Generated
            if ($plan->isDraft() || $plan->isPendingApproval() || $plan->isApproved()) {
                $plan->status = ProductionPlan::STATUS_MRP_GENERATED;
                $plan->save();
            }
        });
    }

    /**
     * Recursively explodes a BOM node.
     */
    private function explodeBom(
        ProductionPlan $plan,
        int $productId,
        int $bomId,
        float $parentQty,
        int $level,
        ?int $sourceItemId
    ): void {
        $bom = ProductionBom::withoutGlobalScopes()->with('items.material')->find($bomId);
        if (!$bom) {
            return;
        }

        foreach ($bom->items as $item) {
            // Required quantity including scrap loss
            $scrapFactor = 1.0 + ($item->material_scrap_percentage / 100);
            $requiredQty = $parentQty * $item->quantity * $scrapFactor;

            // Prep values (stock default 0)
            $available = 0.0;
            $reserved = 0.0;
            $shortage = $requiredQty;

            // Save snapshot requirement
            ProductionPlanRequirement::create([
                'tenant_id' => $plan->tenant_id,
                'production_plan_id' => $plan->id,
                'bom_item_id' => $item->id,
                'product_id' => $item->material_id,
                'bom_level' => $level,
                'required_quantity' => $requiredQty,
                'available_quantity' => $available,
                'reserved_quantity' => $reserved,
                'shortage_quantity' => $shortage,
                'uom_id' => $item->uom_id,
                'source_item_id' => $productId,
                'status' => 'pending',
            ]);

            // If component is semi-finished or finished, check for child BOM
            if ($item->material->type === 'semi_finished' || $item->material->type === 'finished_good') {
                $childBomId = $item->child_bom_id;
                
                // Fallback to default approved BOM if no specific child BOM link is set
                if (!$childBomId) {
                    $defaultChildBom = ProductionBom::withoutGlobalScopes()
                        ->where('tenant_id', $plan->tenant_id)
                        ->where('product_id', $item->material_id)
                        ->where('status', 'approved')
                        ->first();
                    $childBomId = $defaultChildBom ? $defaultChildBom->id : null;
                }

                if ($childBomId) {
                    $this->explodeBom(
                        $plan,
                        $item->material_id,
                        $childBomId,
                        $requiredQty,
                        $level + 1,
                        $item->material_id
                    );
                }
            }
        }
    }

    /**
     * Compute MRP execution summary metrics.
     */
    public function getExecutionSummary(ProductionPlan $plan): array
    {
        $requirements = $plan->requirements()->with('product')->get();
        $operations = $plan->operations()->with('workCenter')->get();

        $totalComponents = 0;
        $totalSubassemblies = 0;
        $estimatedMaterialCost = 0.0;

        foreach ($requirements as $req) {
            if ($req->product->type === 'semi_finished') {
                $totalSubassemblies++;
            } else {
                $totalComponents++;
            }
            $unitCost = (float) ($req->product->unit_cost ?? 0.0);
            // Sum material cost only for leaf components
            if ($req->product->type === 'raw_material') {
                $estimatedMaterialCost += ($req->required_quantity * $unitCost);
            }
        }

        $estimatedManufacturingCost = 0.0;
        $totalRequiredMinutes = 0.0;
        $totalAvailableMinutes = 0.0;

        $days = $plan->start_date->diffInDays($plan->end_date) + 1;

        foreach ($operations as $op) {
            $totalRequiredMinutes += $op->total_time_minutes;
            
            // Labor + Machine costs
            if ($op->routingOperation) {
                $laborCost = $op->total_time_minutes * (float) $op->routingOperation->labor_cost_rate;
                $machineCost = $op->total_time_minutes * (float) $op->routingOperation->machine_cost_rate;
                $estimatedManufacturingCost += ($laborCost + $machineCost);
            }

            // Overhead costs
            $wc = $op->workCenter;
            if ($wc) {
                $overheadCost = $op->total_time_minutes * ((float) $wc->overhead_rate / 60.0);
                $estimatedManufacturingCost += $overheadCost;

                if ($wc->capacity_per_hour !== null && $wc->capacity_per_hour > 0) {
                    $efficiency = $wc->efficiency_percentage > 0 ? ($wc->efficiency_percentage / 100) : 1.0;
                    $wcAvailable = 8.0 * $days * $efficiency * $wc->capacity_per_hour * 60.0;
                    $totalAvailableMinutes += $wcAvailable;
                }
            }
        }

        $utilization = 0.0;
        if ($totalAvailableMinutes > 0) {
            $utilization = round(($totalRequiredMinutes / $totalAvailableMinutes) * 100, 1);
        }

        return [
            'total_components' => $totalComponents,
            'total_subassemblies' => $totalSubassemblies,
            'total_operations' => $operations->count(),
            'estimated_material_cost' => $estimatedMaterialCost,
            'estimated_manufacturing_cost' => $estimatedManufacturingCost,
            'capacity_utilization' => $utilization,
        ];
    }
}
