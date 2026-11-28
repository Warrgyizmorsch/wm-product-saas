<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\RoutingOperation;
use Illuminate\Support\Collection;

class ProductionCostService
{
    /**
     * Resolve primary material rows for a BOM.
     * Excludes both alternative material rows and materials allocated to alternative operations.
     */
    private function primaryMaterialAllocations(ProductionBom $bom): Collection
    {
        $bom->loadMissing(['operationMaterials.material', 'operationMaterials.routingOperation', 'items.material']);

        if ($bom->operationMaterials->isNotEmpty()) {
            return $bom->operationMaterials->filter(function ($item) {
                if ($item->is_alternative) {
                    return false;
                }
                if ($item->routingOperation?->is_alternative) {
                    return false;
                }
                return true;
            })->values();
        }

        return $bom->items->filter(fn ($item) => !$item->is_alternative)->values();
    }

    /**
     * Resolve gross quantity for an allocation or legacy BOM item.
     */
    private function allocationGrossQuantity(object $item): float
    {
        if (isset($item->gross_quantity) && $item->gross_quantity !== null) {
            return (float) $item->gross_quantity;
        }

        if (method_exists($item, 'calculateGrossQuantity')) {
            return (float) $item->calculateGrossQuantity();
        }

        return (float) $item->quantity * (1.0 + (((float) ($item->material_scrap_percentage ?? 0.0)) / 100.0));
    }

    /**
     * Resolve per-unit routing cost layers and once-per-run setup cost for one operation.
     * Uses RT-IMPL-14 saved operation figures when present; falls back to rate calculation for legacy rows.
     *
     * @return array{labor: float, machine: float, subcontract: float, setup_per_run: float}
     */
    public function resolveOperationUnitCosts(RoutingOperation $operation): array
    {
        if ($operation->cost_per_unit !== null) {
            if ($operation->is_external) {
                return [
                    'labor' => 0.0,
                    'machine' => 0.0,
                    'subcontract' => (float) $operation->cost_per_unit,
                    'setup_per_run' => 0.0,
                ];
            }

            $laborPerUnit = (float) ($operation->labor_cost_per_unit ?? 0.0);
            $machinePerUnit = $operation->section_manual_production_id
                ? 0.0
                : (float) ($operation->machine_cost_per_unit ?? 0.0);

            if ($operation->labor_cost_per_unit === null && $operation->machine_cost_per_unit === null) {
                $laborPerUnit = (float) $operation->cost_per_unit;
            }

            return [
                'labor' => $laborPerUnit,
                'machine' => $machinePerUnit,
                'subcontract' => 0.0,
                'setup_per_run' => $operation->setupCostPerRun(),
            ];
        }

        // Legacy fallback for operations created without RT-IMPL-14 saved per-unit figures
        $yieldFactor = ($operation->expected_yield_percentage > 0)
            ? (100.0 / $operation->expected_yield_percentage)
            : 1.0;

        if ($operation->is_external) {
            return [
                'labor' => 0.0,
                'machine' => 0.0,
                'subcontract' => 0.0, // SF-1: no rate on the routing — the job work is charged on the vendor bill
                'setup_per_run' => 0.0,
            ];
        }

        $activeMinutes = (float) $operation->setup_time_minutes + (float) $operation->processing_time_minutes;
        $laborRate = (float) $operation->labor_cost_rate;
        if ($laborRate <= 0.0) {
            if ($operation->section_manual_production_id && $operation->section) {
                $sectionCosts = app(SectionCalculationService::class)->getSectionResourceCosts($operation->section);
                foreach ($sectionCosts['manual_batches'] as $mb) {
                    if ((int) $mb['id'] === (int) $operation->section_manual_production_id) {
                        $laborRate = ($mb['labor_cost_per_hour'] ?? 0.0) / 60.0;
                        break;
                    }
                }
            } elseif ($operation->machine_id && $operation->section) {
                $sectionCosts = app(SectionCalculationService::class)->getSectionResourceCosts($operation->section);
                $laborRate = ($sectionCosts['operator_labor_cost_per_machine_per_hour'] ?? 0.0) / 60.0;
            } else {
                $laborRate = $operation->workCenter ? ($operation->workCenter->calculatedLaborCostPerHour() / 60.0) : 0.0;
            }
        }

        $machineRate = 0.0;
        if (!$operation->section_manual_production_id) {
            $machineRate = (float) $operation->machine_cost_rate;
            if ($machineRate <= 0.0 && $operation->machine) {
                $machineRate = ((float) $operation->machine->operating_cost_per_hour) / 60.0;
            }
        }

        return [
            'labor' => $activeMinutes * $laborRate * $yieldFactor,
            'machine' => $activeMinutes * $machineRate * $yieldFactor,
            'subcontract' => 0.0,
            'setup_per_run' => 0.0,
        ];
    }

    /**
     * Calculate total primary material cost of a BOM (scaled to its base quantity).
     * Formula: sum(gross_qty * unit_cost) across primary operations and primary materials only.
     */
    public function calculateMaterialCost(ProductionBom $bom): float
    {
        $totalCost = 0.0;
        foreach ($this->primaryMaterialAllocations($bom) as $item) {
            $grossQty = $this->allocationGrossQuantity($item);
            $unitCost = (float) ($item->material?->unit_cost ?? 0.0);
            $totalCost += $grossQty * $unitCost;
        }

        return $totalCost;
    }

    /**
     * Calculate total routing labor cost of a BOM (scaled to its base quantity).
     */
    public function calculateLaborCost(ProductionBom $bom): float
    {
        $bom->loadMissing('routing.operations.workCenter', 'routing.operations.section', 'routing.operations.machine', 'routing.operations.manualProduction');
        $routing = $bom->routing;
        if (!$routing) {
            return 0.0;
        }

        $totalCost = 0.0;
        foreach ($routing->operations as $operation) {
            if ($operation->is_alternative || $operation->is_external) {
                continue;
            }

            $costs = $this->resolveOperationUnitCosts($operation);
            $totalCost += $costs['labor'] * (float) $bom->base_quantity;
        }

        return $totalCost;
    }

    /**
     * Calculate total routing machine cost of a BOM (scaled to its base quantity).
     */
    public function calculateMachineCost(ProductionBom $bom): float
    {
        $bom->loadMissing('routing.operations.machine');
        $routing = $bom->routing;
        if (!$routing) {
            return 0.0;
        }

        $totalCost = 0.0;
        foreach ($routing->operations as $operation) {
            if ($operation->is_alternative || $operation->is_external || $operation->section_manual_production_id) {
                continue;
            }

            $costs = $this->resolveOperationUnitCosts($operation);
            $totalCost += $costs['machine'] * (float) $bom->base_quantity;
        }

        return $totalCost;
    }

    /**
     * Calculate total routing subcontracting cost of a BOM (scaled to its base quantity).
     */
    public function calculateRoutingSubcontractCost(ProductionBom $bom): float
    {
        $bom->loadMissing('routing.operations');
        $routing = $bom->routing;
        if (!$routing) {
            return 0.0;
        }

        $totalCost = 0.0;
        foreach ($routing->operations as $operation) {
            if ($operation->is_alternative || !$operation->is_external) {
                continue;
            }

            $costs = $this->resolveOperationUnitCosts($operation);
            $totalCost += $costs['subcontract'] * (float) $bom->base_quantity;
        }

        return $totalCost;
    }

    /**
     * Calculate total once-per-run setup cost across primary non-external operations of a BOM's routing.
     */
    public function calculateSetupCostPerRun(ProductionBom $bom): float
    {
        $bom->loadMissing('routing.operations');
        $routing = $bom->routing;
        if (!$routing) {
            return 0.0;
        }

        $setupTotal = 0.0;
        foreach ($routing->operations as $operation) {
            if ($operation->is_alternative || $operation->is_external) {
                continue;
            }

            $costs = $this->resolveOperationUnitCosts($operation);
            $setupTotal += $costs['setup_per_run'];
        }

        return $setupTotal;
    }

    /**
     * Calculate total overhead cost of a BOM (scaled to its base quantity).
     * Overhead is currently on hold (out of scope for this phase).
     */
    public function calculateOverheadCost(ProductionBom $bom): float
    {
        return 0.0;
    }

    /**
     * Calculate cost of primary material scrap loss.
     * Formula: sum(quantity * (scrap_pct/100) * unit_cost) across primary operations and primary materials only.
     */
    public function calculateScrapAdjustment(ProductionBom $bom): float
    {
        $scrapCost = 0.0;
        foreach ($this->primaryMaterialAllocations($bom) as $item) {
            $scrapPct = (float) ($item->material_scrap_percentage ?? 0.0);
            $scrapQty = (float) $item->quantity * ($scrapPct / 100.0);
            $unitCost = (float) ($item->material?->unit_cost ?? 0.0);
            $scrapCost += $scrapQty * $unitCost;
        }

        return $scrapCost;
    }

    /**
     * Calculate total per-unit routing cost (Labor + Machine + Subcontract) scaled to base quantity.
     * Setup cost per run is returned separately via calculateSetupCostPerRun().
     */
    public function calculateRoutingCost(ProductionBom $bom): float
    {
        return $this->calculateLaborCost($bom)
            + $this->calculateMachineCost($bom)
            + $this->calculateRoutingSubcontractCost($bom);
    }

    /**
     * Calculate total manufacturing cost (Material Cost + Routing Cost + Overhead Cost).
     */
    public function calculateTotalManufacturingCost(ProductionBom $bom): float
    {
        return $this->calculateMaterialCost($bom)
             + $this->calculateRoutingCost($bom)
             + $this->calculateOverheadCost($bom);
    }

    /**
     * Job-work cost of a production order (SF-5, docs/production_ui_rules_review_2026-11-27.md). There is no rate on
     * the routing and no PO, so the only figure is what the vendors billed: Service Bills made from the order's
     * subcontract receipt GRNs (pre-tax subtotal, cancelled bills left out). estimated / committed keep the callers' shape.
     */
    public function calculateSubcontractCost(\App\Domains\Production\Models\ProductionOrder $order): array
    {
        $receiptIds = \App\Domains\Production\Models\ProductionSubcontractReceipt::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->pluck('id');

        $actualCost = $receiptIds->isEmpty() ? 0.0 : (float) \Illuminate\Support\Facades\DB::table('vendor_bills')
            ->join('goods_receipt_notes', 'goods_receipt_notes.id', '=', 'vendor_bills.goods_receipt_note_id')
            ->where('vendor_bills.tenant_id', $order->tenant_id)
            ->where('goods_receipt_notes.source_type', \App\Domains\Purchase\Services\GoodsReceiptNoteService::SOURCE_SUBCONTRACT_RECEIPT)
            ->whereIn('goods_receipt_notes.source_id', $receiptIds)
            ->whereRaw("LOWER(COALESCE(vendor_bills.status, '')) != ?", ['cancelled'])
            ->sum('vendor_bills.subtotal');

        return [
            'estimated' => 0.0,
            'committed' => 0.0,
            'actual' => round($actualCost, 4),
            'authoritative' => round($actualCost, 4),
        ];
    }

    /**
     * Calculate cost summary details (primary operations & primary materials only).
     */
    public function calculateCost(ProductionBom $bom): array
    {
        $materialCost = $this->calculateMaterialCost($bom);
        $scrapAdjustment = $this->calculateScrapAdjustment($bom);
        $laborCost = $this->calculateLaborCost($bom);
        $machineCost = $this->calculateMachineCost($bom);
        $subcontractCost = $this->calculateRoutingSubcontractCost($bom);
        $overheadCost = $this->calculateOverheadCost($bom);
        $setupCostPerRun = $this->calculateSetupCostPerRun($bom);
        $routingCost = $laborCost + $machineCost + $subcontractCost;
        $totalCost = $materialCost + $routingCost + $overheadCost;
        $baseQty = (float) $bom->base_quantity > 0 ? (float) $bom->base_quantity : 1.0;

        return [
            'material_cost'     => $materialCost,
            'net_material_cost' => $materialCost - $scrapAdjustment,
            'labor_cost'        => $laborCost,
            'machine_cost'      => $machineCost,
            'subcontract_cost'  => $subcontractCost,
            'overhead_cost'     => $overheadCost,
            'scrap_adjustment'  => $scrapAdjustment,
            'setup_cost_per_run'=> $setupCostPerRun,
            'routing_cost'      => $routingCost,
            'total_cost'        => $totalCost,
            'cost_per_unit'     => $totalCost / $baseQty,
        ];
    }

    /**
     * Calculate multi-level rolled-up BOM cost by exploding chosen sub-BOMs (primary operations & materials only).
     *
     * @param  array<int, bool>  $visitedProductIds
     */
    public function calculateMultiLevelRollupCost(ProductionBom $bom, ?float $quantity = null, array $visitedProductIds = []): array
    {
        $bom->loadMissing([
            'routing.operations.workCenter',
            'routing.operations.section',
            'routing.operations.machine',
            'routing.operations.manualProduction',
            'operationMaterials.material',
            'operationMaterials.routingOperation',
            'operationMaterials.childBom',
            'items.material',
        ]);

        $baseQty = (float) $bom->base_quantity > 0 ? (float) $bom->base_quantity : 1.0;
        $targetQty = $quantity !== null && $quantity > 0 ? $quantity : $baseQty;
        $multiplier = $targetQty / $baseQty;

        $ownLabor = 0.0;
        $ownMachine = 0.0;
        $ownSubcontract = 0.0;
        $ownSetupPerRun = 0.0;

        if ($bom->routing) {
            foreach ($bom->routing->operations as $op) {
                if ($op->is_alternative) {
                    continue;
                }
                $u = $this->resolveOperationUnitCosts($op);
                $ownLabor += $u['labor'] * $targetQty;
                $ownMachine += $u['machine'] * $targetQty;
                $ownSubcontract += $u['subcontract'] * $targetQty;
                $ownSetupPerRun += $u['setup_per_run'];
            }
        }
        $ownRouting = $ownLabor + $ownMachine + $ownSubcontract;

        if ($bom->product_id) {
            $visitedProductIds[$bom->product_id] = true;
        }

        $directMaterialCost = 0.0;
        $rolledRawMaterialCost = 0.0;
        $rolledScrapCost = 0.0;
        $rolledSubRoutingCost = 0.0;
        $rolledSubSetupPerRun = 0.0;
        $itemRollups = [];

        foreach ($this->primaryMaterialAllocations($bom) as $item) {
            $netQty = (float) $item->quantity * $multiplier;
            $grossQty = $this->allocationGrossQuantity($item) * $multiplier;
            $unitCost = (float) ($item->material?->unit_cost ?? 0.0);
            $directLineCost = $grossQty * $unitCost;
            $directMaterialCost += $directLineCost;

            $childBom = ($item instanceof \App\Domains\Production\Models\RoutingOperationMaterial && $item->child_bom_id)
                ? ProductionBom::withoutGlobalScopes()->whereNull('deleted_at')->find($item->child_bom_id)
                : null;

            $canRecurse = $childBom && $item->material_id && !isset($visitedProductIds[$item->material_id]);

            if ($canRecurse) {
                $sub = $this->calculateMultiLevelRollupCost($childBom, $grossQty, $visitedProductIds);
                $rolledRawMaterialCost += $sub['rolled_raw_material_cost'];
                $rolledScrapCost += $sub['rolled_scrap_cost'];
                $rolledSubRoutingCost += $sub['rolled_routing_cost'];
                $rolledSubSetupPerRun += $sub['rolled_setup_cost_per_run'];

                $itemRollups[$item->id] = [
                    'has_sub_bom' => true,
                    'sub_bom_number' => $childBom->bom_number,
                    'rollup_cost' => $sub['rolled_total_excl_setup'],
                    'raw_material_cost' => $sub['rolled_raw_material_cost'],
                    'sub_routing_cost' => $sub['rolled_routing_cost'],
                    'setup_cost_per_run' => $sub['rolled_setup_cost_per_run'],
                ];
            } else {
                $rolledRawMaterialCost += $directLineCost;
                $rolledScrapCost += max(0.0, $grossQty - $netQty) * $unitCost;

                $itemRollups[$item->id] = [
                    'has_sub_bom' => false,
                    'sub_bom_number' => null,
                    'rollup_cost' => $directLineCost,
                    'raw_material_cost' => $directLineCost,
                    'sub_routing_cost' => 0.0,
                    'setup_cost_per_run' => 0.0,
                ];
            }
        }

        $rolledRoutingCost = $ownRouting + $rolledSubRoutingCost;
        $rolledSetupPerRun = $ownSetupPerRun + $rolledSubSetupPerRun;
        $rolledTotalExclSetup = $rolledRawMaterialCost + $rolledRoutingCost;

        return [
            'quantity' => $targetQty,
            'direct_material_cost' => $directMaterialCost,
            'rolled_raw_material_cost' => $rolledRawMaterialCost,
            'rolled_scrap_cost' => $rolledScrapCost,
            'own_labor_cost' => $ownLabor,
            'own_machine_cost' => $ownMachine,
            'own_subcontract_cost' => $ownSubcontract,
            'own_routing_cost' => $ownRouting,
            'own_setup_cost_per_run' => $ownSetupPerRun,
            'rolled_sub_routing_cost' => $rolledSubRoutingCost,
            'rolled_routing_cost' => $rolledRoutingCost,
            'rolled_setup_cost_per_run' => $rolledSetupPerRun,
            'rolled_total_excl_setup' => $rolledTotalExclSetup,
            'rolled_cost_per_unit' => $targetQty > 0 ? ($rolledTotalExclSetup / $targetQty) : 0.0,
            'item_rollups' => $itemRollups,
        ];
    }

    /**
     * Estimate costs for a specific ProductionOrder using its reservations and routing snapshot.
     */
    public function estimateOrderCost(\App\Domains\Production\Models\ProductionOrder $order): array
    {
        $materialCost = 0.0;
        $order->loadMissing('reservations.product');

        foreach ($order->reservations as $res) {
            $unitCost = (float) ($res->product?->unit_cost ?? $res->product?->cost_price ?? 0.0);
            $materialCost += (float) $res->quantity_planned * $unitCost;
        }

        $routingCost = 0.0;
        if ($order->bom) {
            $routingCost = $this->calculateRoutingCost($order->bom);
        }

        return [
            'material_cost' => $materialCost,
            'routing_cost' => $routingCost,
            'total_cost' => $materialCost + $routingCost,
        ];
    }
}
