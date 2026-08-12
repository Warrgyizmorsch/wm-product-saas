<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MrpShortageService
{
    /**
     * Calculate multi-level BOM explosion and net stock shortages for given demand inputs.
     *
     * @param array $demandInputs Array of demand items:
     *      [
     *          ['product_id' => int, 'quantity' => float, 'warehouse_id' => ?int, 'source_ref' => ?string],
     *          ...
     *      ]
     * @param int $tenantId
     * @param int|null $defaultWarehouseId
     * @return array Calculated tree, consolidated shortages, and summary metrics.
     */
    public function calculateShortages(array $demandInputs, int $tenantId, ?int $defaultWarehouseId = null): array
    {
        $treeNodes = [];
        $consolidatedShortages = [];
        $summary = [
            'total_demanded_items' => count($demandInputs),
            'mfg_products_count' => 0,
            'subassemblies_count' => 0,
            'shortage_items_count' => 0,
            'estimated_pr_total_cost' => 0.0,
        ];

        foreach ($demandInputs as $input) {
            $productId = (int) ($input['product_id'] ?? 0);
            $requiredQty = (float) ($input['quantity'] ?? 0.0);
            $warehouseId = !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : $defaultWarehouseId;
            $sourceRef = $input['source_ref'] ?? 'Demand';

            if ($productId <= 0 || $requiredQty <= 0) {
                continue;
            }

            $product = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($productId);

            if (!$product) {
                continue;
            }

            $visited = [];
            $nodeResult = $this->explodeNode(
                $product,
                $requiredQty,
                $tenantId,
                $warehouseId,
                1,
                $visited,
                $consolidatedShortages,
                $summary,
                $sourceRef
            );

            if (!empty($nodeResult)) {
                $treeNodes[] = $nodeResult;
            }
        }

        // Format consolidated shortages list
        $consolidatedList = array_values($consolidatedShortages);
        $summary['shortage_items_count'] = count($consolidatedList);
        $summary['estimated_pr_total_cost'] = array_sum(array_column($consolidatedList, 'total_cost'));

        return [
            'tree' => $treeNodes,
            'consolidated' => $consolidatedList,
            'summary' => $summary,
        ];
    }

    /**
     * Recursively explode a product node taking stock and reservation into account.
     */
    private function explodeNode(
        Product $product,
        float $requiredQty,
        int $tenantId,
        ?int $warehouseId,
        int $level,
        array $visited,
        array &$consolidatedShortages,
        array &$summary,
        ?string $sourceRef = null
    ): array {
        if (isset($visited[$product->id])) {
            // Circular dependency detection fallback
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'level' => $level,
                'error' => 'Circular reference detected',
            ];
        }

        $visited[$product->id] = true;

        // Fetch current stock snapshot
        $stockSnapshot = $this->getInventorySnapshot($tenantId, $product->id, $warehouseId);
        $onHand = $stockSnapshot['on_hand'];
        $reserved = $stockSnapshot['reserved'];
        $available = $stockSnapshot['available'];

        // Available is OnHand - Reserved
        $netShortage = max(0.0, $requiredQty - $available);
        $mfgRequiredQty = $netShortage;

        // Find active approved BOM for this product
        $bom = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->with(['items.material', 'items.uom'])
            ->first();

        $isManufactured = ($bom !== null || $product->planning_type === 'manufacture' || $product->type === 'finished_good' || $product->type === 'semi_finished');
        
        if ($isManufactured && $level === 1) {
            $summary['mfg_products_count']++;
        } elseif ($isManufactured && $level > 1) {
            $summary['subassemblies_count']++;
        }

        $node = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type ?? 'goods',
            'planning_type' => $product->planning_type ?? ($bom ? 'manufacture' : 'purchase'),
            'uom_code' => $product->uom ? $product->uom->code : 'PCS',
            'unit_cost' => (float) ($product->unit_cost ?? $product->cost_price ?? 0.0),
            'level' => $level,
            'required_qty' => $requiredQty,
            'on_hand_qty' => $onHand,
            'reserved_qty' => $reserved,
            'available_qty' => $available,
            'net_shortage_qty' => $netShortage,
            'mfg_required_qty' => $mfgRequiredQty,
            'has_bom' => $bom !== null,
            'bom_number' => $bom?->bom_number,
            'source_ref' => $sourceRef,
            'children' => [],
        ];

        if ($bom && $mfgRequiredQty > 0) {
            $baseQty = $bom->base_quantity > 0 ? $bom->base_quantity : 1.0;
            $multiplier = $mfgRequiredQty / $baseQty;

            foreach ($bom->items as $item) {
                $childMaterial = $item->material;
                if (!$childMaterial) continue;

                $scrapPct = (float) $item->material_scrap_percentage;
                $scrapFactor = 1.0 + ($scrapPct / 100.0);
                $childRequiredQty = $item->quantity * $multiplier * $scrapFactor;

                // Recursive explosion for child item
                $childNode = $this->explodeNode(
                    $childMaterial,
                    $childRequiredQty,
                    $tenantId,
                    $warehouseId,
                    $level + 1,
                    $visited,
                    $consolidatedShortages,
                    $summary,
                    "Parent: {$product->name}"
                );

                $childNode['bom_qty_per_unit'] = $item->quantity;
                $childNode['scrap_percentage'] = $scrapPct;

                $node['children'][] = $childNode;
            }
        } else {
            // Direct Purchase item or leaf Raw Material item with no further BOM explosion
            if ($netShortage > 0) {
                $pId = $product->id;
                $unitCost = (float) ($product->unit_cost ?? $product->cost_price ?? 0.0);

                $prApproved = $this->getPrApprovedQty($tenantId, $product->id, $warehouseId);
                $prDraft = $this->getPrDraftQty($tenantId, $product->id, $warehouseId);
                $unprocuredShortage = max(0.0, $netShortage - $prApproved);

                if (isset($consolidatedShortages[$pId])) {
                    $consolidatedShortages[$pId]['required_qty'] += $requiredQty;
                    $consolidatedShortages[$pId]['gross_shortage_qty'] += $netShortage;
                    $consolidatedShortages[$pId]['net_shortage_qty'] = max(0.0, $consolidatedShortages[$pId]['required_qty'] - ($consolidatedShortages[$pId]['available_qty'] + $prApproved));
                    $consolidatedShortages[$pId]['suggested_pr_qty'] = $consolidatedShortages[$pId]['net_shortage_qty'];
                    $consolidatedShortages[$pId]['total_cost'] = $consolidatedShortages[$pId]['suggested_pr_qty'] * $unitCost;
                    if ($sourceRef && !in_array($sourceRef, $consolidatedShortages[$pId]['sources'])) {
                        $consolidatedShortages[$pId]['sources'][] = $sourceRef;
                    }
                } else {
                    $consolidatedShortages[$pId] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'type' => $product->type ?? 'raw_material',
                        'uom_code' => $product->uom ? $product->uom->code : 'PCS',
                        'on_hand_qty' => $onHand,
                        'reserved_qty' => $reserved,
                        'available_qty' => $available,
                        'warehouse_breakdown' => $stockSnapshot['breakdown'] ?? [],
                        'required_qty' => $requiredQty,
                        'gross_shortage_qty' => $netShortage,
                        'pr_approved_qty' => $prApproved,
                        'pr_draft_qty' => $prDraft,
                        'net_shortage_qty' => $unprocuredShortage,
                        'suggested_pr_qty' => $unprocuredShortage,
                        'unit_cost' => $unitCost,
                        'total_cost' => $unprocuredShortage * $unitCost,
                        'preferred_vendor_id' => $product->preferred_vendor_id,
                        'preferred_vendor_name' => $product->vendor ? $product->vendor->name : null,
                        'sources' => $sourceRef ? [$sourceRef] : [],
                    ];
                }
            }
        }

        return $node;
    }

    /**
     * Get strictly Approved PR quantity for a product (Recent Active Window: 30 days).
     */
    public function getPrApprovedQty(int $tenantId, int $productId, ?int $warehouseId = null, int $daysWindow = 30): float
    {
        $query = DB::table('purchase_requisition_items')
            ->join('purchase_requisitions', 'purchase_requisitions.id', '=', 'purchase_requisition_items.purchase_requisition_id')
            ->where('purchase_requisitions.tenant_id', $tenantId)
            ->where('purchase_requisition_items.product_id', $productId)
            ->whereIn(DB::raw('LOWER(purchase_requisitions.status)'), ['approved', 'confirm', 'confirmed'])
            ->where('purchase_requisitions.created_at', '>=', now()->subDays($daysWindow));

        if ($warehouseId) {
            $query->where('purchase_requisition_items.warehouse_id', $warehouseId);
        }

        return max(0.0, (float) $query->sum('purchase_requisition_items.quantity'));
    }

    /**
     * Get Draft / Pending PR quantity for a product (Recent Active Window: 30 days).
     */
    public function getPrDraftQty(int $tenantId, int $productId, ?int $warehouseId = null, int $daysWindow = 30): float
    {
        $query = DB::table('purchase_requisition_items')
            ->join('purchase_requisitions', 'purchase_requisitions.id', '=', 'purchase_requisition_items.purchase_requisition_id')
            ->where('purchase_requisitions.tenant_id', $tenantId)
            ->where('purchase_requisition_items.product_id', $productId)
            ->whereIn(DB::raw('LOWER(purchase_requisitions.status)'), ['draft', 'pending', 'submitted'])
            ->where('purchase_requisitions.created_at', '>=', now()->subDays($daysWindow));

        if ($warehouseId) {
            $query->where('purchase_requisition_items.warehouse_id', $warehouseId);
        }

        return max(0.0, (float) $query->sum('purchase_requisition_items.quantity'));
    }

    /**
     * Get snapshot of stock, reserved stock, and net available stock for a product across warehouses.
     */
    public function getInventorySnapshot(int $tenantId, int $productId, ?int $warehouseId = null): array
    {
        $query = ProductWarehouseStock::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks = $query->with('warehouse')->get();

        $onHand = 0.0;
        $reserved = 0.0;
        $breakdown = [];

        foreach ($stocks as $stock) {
            $whOnHand = max(0.0, (float) $stock->quantity);
            $whReserved = max(0.0, (float) $stock->reserved_qty);
            $whAvailable = max(0.0, $whOnHand - $whReserved);

            $onHand += $whOnHand;
            $reserved += $whReserved;

            if ($stock->warehouse) {
                $breakdown[] = [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name,
                    'on_hand' => $whOnHand,
                    'reserved' => $whReserved,
                    'available' => $whAvailable,
                ];
            }
        }

        $available = max(0.0, $onHand - $reserved);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Generate a consolidated Purchase Requisition (PR) for selected shortage items.
     *
     * @param array $itemsToProcure Array of selected items to buy:
     *      [
     *          ['product_id' => int, 'quantity' => float, 'unit_cost' => float, 'warehouse_id' => ?int],
     *          ...
     *      ]
     * @param int $tenantId
     * @param int $userId
     * @param string|null $notes
     * @param string|null $sourceType
     * @param int|null $sourceId
     * @return PurchaseRequisition
     */
    public function generateConsolidatedPr(
        array $itemsToProcure,
        int $tenantId,
        int $userId,
        ?string $notes = null,
        ?string $sourceType = 'mrp_explosion',
        ?int $sourceId = null
    ): PurchaseRequisition {
        return DB::transaction(function () use ($itemsToProcure, $tenantId, $userId, $notes, $sourceType, $sourceId) {
            $validItems = array_filter($itemsToProcure, fn($item) => !empty($item['product_id']) && (float)($item['quantity'] ?? 0) > 0);

            if (empty($validItems)) {
                throw new InvalidArgumentException('No valid items with quantity > 0 were selected for Purchase Requisition generation.');
            }

            // Generate Next PR Requisition Number
            $year = now()->format('Y');
            $prefix = "PR-{$year}-";

            $latest = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->requisition_number);
                $nextNum = intval($lastNumStr) + 1;
            }

            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            // Create Master PR Record
            $pr = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requested_by' => $userId,
                'requisition_date' => now()->toDateString(),
                'status' => 'Draft',
                'notes' => $notes ?: 'Generated automatically from MRP Multi-Level BOM Explosion & Shortage Analysis.',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            // Add Line Items
            foreach ($validItems as $itemData) {
                $productId = (int) $itemData['product_id'];
                $qty = (float) $itemData['quantity'];
                $unitCost = isset($itemData['unit_cost']) ? (float) $itemData['unit_cost'] : 0.0;
                $warehouseId = !empty($itemData['warehouse_id']) ? (int) $itemData['warehouse_id'] : null;

                PurchaseRequisitionItem::create([
                    'tenant_id' => $tenantId,
                    'purchase_requisition_id' => $pr->id,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $qty,
                    'estimated_cost' => $unitCost,
                ]);
            }

            return $pr;
        });
    }

    /**
     * Load pending Material Requirements (MRs) ready for MRP explosion.
     */
    public function getPendingMaterialRequirements(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return MaterialRequirement::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['Delivered', 'Cancelled'])
            ->with(['items.product', 'items.warehouse', 'salesOrder'])
            ->orderBy('id', 'desc')
            ->get();
    }
}
