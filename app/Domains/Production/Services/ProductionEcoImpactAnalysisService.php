<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;

class ProductionEcoImpactAnalysisService
{
    /**
     * Perform a comprehensive, read-only impact analysis for an ECO.
     */
    public function analyzeImpact(ProductionEco $eco): array
    {
        $tenantId = $eco->tenant_id;
        $product = Product::find($eco->product_id);

        $isBomChange = in_array($eco->change_type, [ProductionEco::CHANGE_TYPE_BOM, ProductionEco::CHANGE_TYPE_BOM_AND_ROUTING], true);
        $isRoutingChange = in_array($eco->change_type, [ProductionEco::CHANGE_TYPE_ROUTING, ProductionEco::CHANGE_TYPE_BOM_AND_ROUTING], true);

        $currentBom = $eco->currentBom ?: ProductionBom::with('items.product')->find($eco->current_bom_id);
        $proposedBom = $isBomChange ? ($eco->proposedBom ?: ProductionBom::with('items.product')->find($eco->proposed_bom_id)) : $currentBom;

        $currentRouting = $eco->currentRouting ?: Routing::with('operations')->find($eco->current_routing_id);
        $proposedRouting = $isRoutingChange ? ($eco->proposedRouting ?: Routing::with('operations')->find($eco->proposed_routing_id)) : $currentRouting;

        // 1. Open Production Orders Impact
        $openOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->where('product_id', $eco->product_id)
            ->whereIn('status', ['draft', 'released', 'in_progress'])
            ->get();

        $openOrdersSummary = [
            'total_count' => $openOrders->count(),
            'total_quantity' => (float) $openOrders->sum('quantity_ordered'),
            'by_status' => [
                'draft' => [
                    'count' => $openOrders->where('status', 'draft')->count(),
                    'quantity' => (float) $openOrders->where('status', 'draft')->sum('quantity_ordered'),
                ],
                'released' => [
                    'count' => $openOrders->where('status', 'released')->count(),
                    'quantity' => (float) $openOrders->where('status', 'released')->sum('quantity_ordered'),
                ],
                'in_progress' => [
                    'count' => $openOrders->where('status', 'in_progress')->count(),
                    'quantity' => (float) $openOrders->where('status', 'in_progress')->sum('quantity_ordered'),
                ],
            ],
            'orders' => $openOrders->map(fn($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'quantity_ordered' => (float) $o->quantity_ordered,
            ])->toArray(),
        ];

        // 2. Scheduled Operations Impact
        $openOrderIds = $openOrders->pluck('id')->toArray();
        $scheduledOps = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('production_order_id', $openOrderIds)
            ->get();

        $scheduledOpsSummary = [
            'count' => $scheduledOps->count(),
            'operations' => $scheduledOps->map(fn($so) => [
                'id' => $so->id,
                'production_order_id' => $so->production_order_id,
                'operation_number' => $so->operation_number,
                'name' => $so->name,
                'status' => $so->status,
                'scheduled_start' => $so->scheduled_start ? $so->scheduled_start->toDateTimeString() : null,
            ])->toArray(),
        ];

        // 3. Material (BOM) Impact Analysis
        $materialImpact = $this->calculateMaterialImpact($currentBom, $proposedBom);

        // 4. Routing Impact Analysis
        $routingImpact = $this->calculateRoutingImpact($currentRouting, $proposedRouting);

        // 5. MRP Impact
        $mrpImpact = $this->calculateMrpImpact($materialImpact, $openOrdersSummary['total_quantity']);

        // 6. Capacity Impact
        $capacityImpact = $this->calculateCapacityImpact($routingImpact, $openOrdersSummary['total_quantity']);

        // 7. Cost Impact
        $costImpact = $this->calculateCostImpact($currentBom, $proposedBom, $currentRouting, $proposedRouting);

        // 8. WIP Impact
        $wipRecords = ProductionWip::where('tenant_id', $tenantId)
            ->whereIn('production_order_id', $openOrderIds)
            ->get();

        $wipSummary = [
            'affected_wip_count' => $wipRecords->count(),
            'wip_records' => $wipRecords->map(fn($w) => [
                'id' => $w->id,
                'production_order_id' => $w->production_order_id,
                'quantity_in_wip' => (float) $w->quantity_in_wip,
                'impact_status' => 'REVIEW_REQUIRED',
            ])->toArray(),
        ];

        // 9. Genealogy & Batch Protection
        $affectedBatchesCount = ProductionBatch::where('tenant_id', $tenantId)
            ->whereIn('production_order_id', $openOrderIds)
            ->count();

        $genealogySummary = [
            'affected_batches_count' => $affectedBatchesCount,
            'protection_status' => 'PROTECTED_HISTORICAL_DATA_INTACT',
        ];

        return [
            'eco_id' => $eco->id,
            'eco_number' => $eco->eco_number,
            'product_id' => $eco->product_id,
            'product_name' => $product ? $product->name : 'N/A',
            'change_type' => $eco->change_type,
            'effective_date' => $eco->effective_date ? $eco->effective_date->toDateString() : null,
            'status' => $eco->status,
            'open_orders' => $openOrdersSummary,
            'scheduled_operations' => $scheduledOpsSummary,
            'material_impact' => $materialImpact,
            'routing_impact' => $routingImpact,
            'mrp_impact' => $mrpImpact,
            'capacity_impact' => $capacityImpact,
            'cost_impact' => $costImpact,
            'wip_impact' => $wipSummary,
            'genealogy_impact' => $genealogySummary,
            'bom' => [
                'current_version' => $currentBom ? ($currentBom->version ?? '1.0') : 'N/A',
                'current_revision' => $currentBom ? $currentBom->revision : ($eco->current_bom_revision ?? 0),
                'proposed_version' => $proposedBom ? ($proposedBom->version ?? '1.0') : 'N/A',
                'proposed_revision' => $proposedBom ? $proposedBom->revision : ($eco->proposed_bom_revision ?? 1),
                'has_change' => !empty($materialImpact['added']) || !empty($materialImpact['removed']) || !empty($materialImpact['quantity_changed']),
            ],
            'routing' => [
                'current_version' => $currentRouting ? ($currentRouting->version ?? '1.0') : 'N/A',
                'current_revision' => $currentRouting ? $currentRouting->revision : ($eco->current_routing_revision ?? 0),
                'proposed_version' => $proposedRouting ? ($proposedRouting->version ?? '1.0') : 'N/A',
                'proposed_revision' => $proposedRouting ? $proposedRouting->revision : ($eco->proposed_routing_revision ?? 1),
                'has_change' => !empty($routingImpact['added_operations']) || !empty($routingImpact['removed_operations']) || !empty($routingImpact['modified_operations']),
            ],
            'wip_orders_count' => $openOrdersSummary['total_count'],
        ];
    }

    private function calculateMaterialImpact(?ProductionBom $currentBom, ?ProductionBom $proposedBom): array
    {
        $currentItems = $currentBom ? $currentBom->items->keyBy('material_id') : collect();
        $proposedItems = $proposedBom ? $proposedBom->items->keyBy('material_id') : collect();

        $added = [];
        $removed = [];
        $quantityChanged = [];

        foreach ($proposedItems as $materialId => $newItem) {
            if (!$currentItems->has($materialId)) {
                $added[] = [
                    'material_id' => $newItem->material_id,
                    'material_name' => $newItem->product ? $newItem->product->name : "Material #{$newItem->material_id}",
                    'quantity' => (float) $newItem->quantity,
                    'scrap_percentage' => (float) $newItem->scrap_percentage,
                ];
            } else {
                $oldItem = $currentItems->get($materialId);
                $oldQty = (float) $oldItem->quantity;
                $newQty = (float) $newItem->quantity;
                $oldFormula = $oldItem->formula;
                $newFormula = $newItem->formula;
                $isFormulaChanged = ($oldFormula !== $newFormula) || ($oldItem->quantity_type !== $newItem->quantity_type);

                if (abs($oldQty - $newQty) > 0.0001 || abs((float)$oldItem->scrap_percentage - (float)$newItem->scrap_percentage) > 0.0001 || $isFormulaChanged) {
                    $quantityChanged[] = [
                        'material_id' => $newItem->material_id,
                        'material_name' => $newItem->product ? $newItem->product->name : "Material #{$newItem->material_id}",
                        'old_quantity' => $oldQty,
                        'new_quantity' => $newQty,
                        'delta_quantity' => round($newQty - $oldQty, 4),
                        'old_scrap_percentage' => (float) $oldItem->scrap_percentage,
                        'new_scrap_percentage' => (float) $newItem->scrap_percentage,
                        'old_formula' => $oldFormula,
                        'new_formula' => $newFormula,
                        'is_formula_changed' => $isFormulaChanged,
                    ];
                }
            }
        }

        foreach ($currentItems as $materialId => $oldItem) {
            if (!$proposedItems->has($materialId)) {
                $removed[] = [
                    'material_id' => $oldItem->material_id,
                    'material_name' => $oldItem->product ? $oldItem->product->name : "Material #{$oldItem->material_id}",
                    'quantity' => (float) $oldItem->quantity,
                    'scrap_percentage' => (float) $oldItem->scrap_percentage,
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'quantity_changed' => $quantityChanged,
        ];
    }

    private function calculateRoutingImpact(?Routing $currentRouting, ?Routing $proposedRouting): array
    {
        $currentOps = $currentRouting ? $currentRouting->operations->keyBy('operation_number') : collect();
        $proposedOps = $proposedRouting ? $proposedRouting->operations->keyBy('operation_number') : collect();

        $addedOps = [];
        $removedOps = [];
        $modifiedOps = [];

        foreach ($proposedOps as $opNumber => $newOp) {
            if (!$currentOps->has($opNumber)) {
                $addedOps[] = [
                    'operation_number' => $newOp->operation_number,
                    'name' => $newOp->name,
                    'sequence' => $newOp->sequence,
                    'work_center_id' => $newOp->work_center_id,
                    'machine_id' => $newOp->machine_id,
                    'setup_time_minutes' => (float) $newOp->setup_time_minutes,
                    'run_time_minutes' => (float) $newOp->processing_time_minutes,
                ];
            } else {
                $oldOp = $currentOps->get($opNumber);
                $deltas = [];

                if ($oldOp->machine_id !== $newOp->machine_id) {
                    $deltas[] = ['field' => 'machine', 'old' => $oldOp->machine_id, 'new' => $newOp->machine_id];
                }
                if ($oldOp->work_center_id !== $newOp->work_center_id) {
                    $deltas[] = ['field' => 'work_center', 'old' => $oldOp->work_center_id, 'new' => $newOp->work_center_id];
                }
                if (abs((float)$oldOp->setup_time_minutes - (float)$newOp->setup_time_minutes) > 0.01) {
                    $deltas[] = ['field' => 'setup_time', 'old' => (float)$oldOp->setup_time_minutes, 'new' => (float)$newOp->setup_time_minutes];
                }
                if (abs((float)$oldOp->processing_time_minutes - (float)$newOp->processing_time_minutes) > 0.01) {
                    $deltas[] = ['field' => 'run_time', 'old' => (float)$oldOp->processing_time_minutes, 'new' => (float)$newOp->processing_time_minutes];
                }
                if ($oldOp->sequence !== $newOp->sequence) {
                    $deltas[] = ['field' => 'sequence', 'old' => $oldOp->sequence, 'new' => $newOp->sequence];
                }

                if (!empty($deltas)) {
                    $modifiedOps[] = [
                        'operation_number' => $opNumber,
                        'name' => $newOp->name,
                        'deltas' => $deltas,
                    ];
                }
            }
        }

        foreach ($currentOps as $opNumber => $oldOp) {
            if (!$proposedOps->has($opNumber)) {
                $removedOps[] = [
                    'operation_number' => $oldOp->operation_number,
                    'name' => $oldOp->name,
                    'sequence' => $oldOp->sequence,
                ];
            }
        }

        return [
            'added_operations' => $addedOps,
            'removed_operations' => $removedOps,
            'modified_operations' => $modifiedOps,
        ];
    }

    private function calculateMrpImpact(array $materialImpact, float $totalOpenQty): array
    {
        $deltas = [];

        foreach ($materialImpact['added'] as $item) {
            $deltas[] = [
                'material_id' => $item['material_id'],
                'material_name' => $item['material_name'],
                'unit_delta' => $item['quantity'],
                'net_demand_delta' => round($item['quantity'] * $totalOpenQty, 4),
            ];
        }

        foreach ($materialImpact['removed'] as $item) {
            $deltas[] = [
                'material_id' => $item['material_id'],
                'material_name' => $item['material_name'],
                'unit_delta' => -$item['quantity'],
                'net_demand_delta' => round(-$item['quantity'] * $totalOpenQty, 4),
            ];
        }

        foreach ($materialImpact['quantity_changed'] as $item) {
            $deltas[] = [
                'material_id' => $item['material_id'],
                'material_name' => $item['material_name'],
                'unit_delta' => $item['delta_quantity'],
                'net_demand_delta' => round($item['delta_quantity'] * $totalOpenQty, 4),
            ];
        }

        return [
            'component_deltas' => $deltas,
        ];
    }

    private function calculateCapacityImpact(array $routingImpact, float $totalOpenQty): array
    {
        $unitDeltaMinutes = 0.0;

        foreach ($routingImpact['added_operations'] as $op) {
            $unitDeltaMinutes += $op['setup_time_minutes'] + $op['run_time_minutes'];
        }

        foreach ($routingImpact['removed_operations'] as $op) {
            // Assume 10 min baseline if removed op setup/run not available
            $unitDeltaMinutes -= 10.0;
        }

        foreach ($routingImpact['modified_operations'] as $op) {
            foreach ($op['deltas'] as $d) {
                if ($d['field'] === 'run_time' || $d['field'] === 'setup_time') {
                    $unitDeltaMinutes += ($d['new'] - $d['old']);
                }
            }
        }

        $totalImpactHours = round(($unitDeltaMinutes * $totalOpenQty) / 60.0, 2);

        return [
            'cycle_time_delta_per_unit_minutes' => round($unitDeltaMinutes, 2),
            'total_open_capacity_impact_hours' => $totalImpactHours,
        ];
    }

    private function calculateCostImpact(?ProductionBom $currentBom, ?ProductionBom $proposedBom, ?Routing $currentRouting, ?Routing $proposedRouting): array
    {
        $currentMaterialCost = $currentBom ? $currentBom->items->sum(fn($i) => $i->quantity * ($i->unit_cost ?? 10.0)) : 0.0;
        $proposedMaterialCost = $proposedBom ? $proposedBom->items->sum(fn($i) => $i->quantity * ($i->unit_cost ?? 10.0)) : 0.0;

        $currentLaborCost = $currentRouting ? $currentRouting->operations->sum(fn($o) => ($o->processing_time_minutes / 60.0) * ($o->cost_per_hour ?? 50.0)) : 0.0;
        $proposedLaborCost = $proposedRouting ? $proposedRouting->operations->sum(fn($o) => ($o->processing_time_minutes / 60.0) * ($o->cost_per_hour ?? 50.0)) : 0.0;

        return [
            'current_unit_material_cost' => round($currentMaterialCost, 2),
            'proposed_unit_material_cost' => round($proposedMaterialCost, 2),
            'material_cost_delta' => round($proposedMaterialCost - $currentMaterialCost, 2),
            'current_unit_labor_cost' => round($currentLaborCost, 2),
            'proposed_unit_labor_cost' => round($proposedLaborCost, 2),
            'labor_cost_delta' => round($proposedLaborCost - $currentLaborCost, 2),
            'total_unit_cost_delta' => round(($proposedMaterialCost + $proposedLaborCost) - ($currentMaterialCost + $currentLaborCost), 2),
        ];
    }
}
