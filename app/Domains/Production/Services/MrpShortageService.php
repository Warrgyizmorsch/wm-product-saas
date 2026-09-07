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
    public function buildShortageTree(
        int $tenantId,
        int $productId,
        float $quantity,
        ?int $warehouseId = null,
        ?string $sourceRef = null,
        ?string $requiredDate = null,
        ?array $supplyTracker = null,
        ?int $level = 1,
        ?array $visited = null,
        ?int $bomId = null,
        array $parameters = []
    ): array {
        $demandInputs = [
            [
                'product_id' => $productId,
                'quantity' => $quantity,
                'warehouse_id' => $warehouseId,
                'source_ref' => $sourceRef ?? 'Demand',
                'required_date' => $requiredDate,
                'parameters' => $parameters,
            ]
        ];

        $res = $this->calculateShortages($demandInputs, $tenantId, $warehouseId);
        return $res['tree'][0] ?? [];
    }

    /**
     * Calculate multi-level BOM explosion and net stock shortages for given demand inputs.
     *
     * @param array $demandInputs Array of demand items:
     *      [
     *          ['product_id' => int, 'quantity' => float, 'warehouse_id' => ?int, 'source_ref' => ?string, 'required_date' => ?string],
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

        // Sort demand inputs chronologically by required_date (earlier demands processed first)
        usort($demandInputs, function ($a, $b) {
            $dateA = !empty($a['required_date']) ? \Illuminate\Support\Carbon::parse($a['required_date'])->timestamp : (!empty($a['date']) ? \Illuminate\Support\Carbon::parse($a['date'])->timestamp : 0);
            $dateB = !empty($b['required_date']) ? \Illuminate\Support\Carbon::parse($b['required_date'])->timestamp : (!empty($b['date']) ? \Illuminate\Support\Carbon::parse($b['date'])->timestamp : 0);
            return $dateA <=> $dateB;
        });

        // Running tracker to prevent double-counting supply across chronological demands
        $supplyTracker = [
            'on_hand_consumed' => [],
            'po_items_consumed' => [],
            'wo_orders_consumed' => [],
        ];

        // Sort demand inputs chronologically by required_date before processing
        usort($demandInputs, function ($a, $b) {
            $dateA = $a['required_date'] ?? ($a['date'] ?? '9999-12-31');
            $dateB = $b['required_date'] ?? ($b['date'] ?? '9999-12-31');
            return strcmp((string)$dateA, (string)$dateB);
        });

        foreach ($demandInputs as $input) {
            $productId = (int) ($input['product_id'] ?? 0);
            $requiredQty = (float) ($input['quantity'] ?? 0.0);
            $warehouseId = !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : $defaultWarehouseId;
            $sourceRef = $input['source_ref'] ?? 'Demand';
            $requiredDateStr = $input['required_date'] ?? ($input['date'] ?? null);
            $requiredDate = $requiredDateStr ? \Illuminate\Support\Carbon::parse($requiredDateStr) : null;
            $parameters = $input['parameters'] ?? [];

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
                $sourceRef,
                $requiredDate,
                $supplyTracker,
                $parameters
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
     * Recursively explode a product node taking stock, open POs, open WOs, and date-awareness into account.
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
        ?string $sourceRef = null,
        ?\Illuminate\Support\Carbon $requiredDate = null,
        array &$supplyTracker = [],
        array $parameters = []
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

        // 1. Fetch current on-hand stock snapshot
        $stockSnapshot = $this->getInventorySnapshot($tenantId, $product->id, $warehouseId);
        $onHand = $stockSnapshot['on_hand'];
        $reserved = $stockSnapshot['reserved'];
        $totalAvailableStock = $stockSnapshot['available'];

        // Compute unconsumed on-hand available stock for this product
        $alreadyConsumedStock = $supplyTracker['on_hand_consumed'][$product->id] ?? 0.0;
        $unconsumedStock = max(0.0, $totalAvailableStock - $alreadyConsumedStock);

        // 2. Fetch open PO supply arriving on or before requiredDate
        $poSupplySnapshot = $this->getOpenPoSupplySnapshot($tenantId, $product->id, $warehouseId, $requiredDate, $supplyTracker);
        $openPoQty = $poSupplySnapshot['usable_qty'];

        // 3. Fetch open Production Order supply completing on or before requiredDate
        $woSupplySnapshot = $this->getOpenProductionOrderSupplySnapshot($tenantId, $product->id, $warehouseId, $requiredDate, $supplyTracker);
        $openWoQty = $woSupplySnapshot['usable_qty'];

        // Total Usable Supply on or before requiredDate
        $usableSupply = $unconsumedStock + $openPoQty + $openWoQty;

        // Net Shortage calculation
        $netShortage = max(0.0, $requiredQty - $usableSupply);
        $mfgRequiredQty = $netShortage;

        // Consume supply chronologically in tracker
        $remDemand = $requiredQty;
        // First consume on-hand stock
        $consumedStock = min($remDemand, $unconsumedStock);
        $supplyTracker['on_hand_consumed'][$product->id] = $alreadyConsumedStock + $consumedStock;
        $remDemand -= $consumedStock;

        // Next consume open PO supply
        if ($remDemand > 0 && !empty($poSupplySnapshot['items'])) {
            foreach ($poSupplySnapshot['items'] as $poItemData) {
                if ($remDemand <= 0) break;
                $poItemId = $poItemData['id'];
                $poAvail = $poItemData['unconsumed_qty'];
                $consumedPo = min($remDemand, $poAvail);
                $supplyTracker['po_items_consumed'][$poItemId] = ($supplyTracker['po_items_consumed'][$poItemId] ?? 0.0) + $consumedPo;
                $remDemand -= $consumedPo;
            }
        }

        // Next consume open Production Order supply
        if ($remDemand > 0 && !empty($woSupplySnapshot['orders'])) {
            foreach ($woSupplySnapshot['orders'] as $woData) {
                if ($remDemand <= 0) break;
                $woId = $woData['id'];
                $woAvail = $woData['unconsumed_qty'];
                $consumedWo = min($remDemand, $woAvail);
                $supplyTracker['wo_orders_consumed'][$woId] = ($supplyTracker['wo_orders_consumed'][$woId] ?? 0.0) + $consumedWo;
                $remDemand -= $consumedWo;
            }
        }

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

        $moq = (float) ($product->minimum_order_qty ?? 0.0);
        $orderMultiple = (float) ($product->order_multiple ?? 0.0);
        $lotSizer = app(MrpLotSizingService::class);
        $nodeLotSizing = $lotSizer->calculatePlannedSupply($netShortage, $moq, $orderMultiple);

        $node = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type ?? 'goods',
            'planning_type' => $product->planning_type ?? ($bom ? 'manufacture' : 'purchase'),
            'uom_code' => $product->uom?->code ?? 'PCS',
            'unit_cost' => (float) ($product->unit_cost ?? $product->cost_price ?? 0.0),
            'level' => $level,
            'required_qty' => $requiredQty,
            'required_date' => $requiredDate ? $requiredDate->toDateString() : null,
            'on_hand_qty' => $onHand,
            'reserved_qty' => $reserved,
            'available_qty' => $totalAvailableStock,
            'open_po_supply_qty' => $openPoQty,
            'open_wo_supply_qty' => $openWoQty,
            'total_usable_supply_qty' => $usableSupply,
            'net_requirement_qty' => $netShortage,
            'net_shortage_qty' => $netShortage,
            'minimum_order_qty' => $moq,
            'order_multiple' => $orderMultiple,
            'planned_supply_qty' => $nodeLotSizing['planned_supply_qty'],
            'lot_sizing_rule' => $nodeLotSizing['rule'],
            'mfg_required_qty' => $mfgRequiredQty,
            'has_bom' => $bom !== null,
            'bom_number' => $bom?->bom_number,
            'source_ref' => $sourceRef,
            'children' => [],
        ];

        if ($bom && $mfgRequiredQty > 0) {
            $baseQty = $bom->base_quantity > 0 ? $bom->base_quantity : 1.0;
            $multiplier = $mfgRequiredQty / $baseQty;

            $evaluator = app(BomFormulaEvaluatorService::class);

            foreach ($bom->items as $item) {
                $childMaterial = $item->material;
                if (!$childMaterial) continue;

                $scrapPct = (float) $item->material_scrap_percentage;
                $scrapFactor = 1.0 + ($scrapPct / 100.0);

                if ($item->isFormula()) {
                    $unitQty = $evaluator->evaluate($item->formula, $parameters ?? [], $bom->product);
                } else {
                    $unitQty = (float) $item->quantity;
                }

                $childRequiredQty = $unitQty * $multiplier * $scrapFactor;

                // Child inherits required date from parent
                $childNode = $this->explodeNode(
                    $childMaterial,
                    $childRequiredQty,
                    $tenantId,
                    $warehouseId,
                    $level + 1,
                    $visited,
                    $consolidatedShortages,
                    $summary,
                    "Parent: {$product->name}",
                    $requiredDate,
                    $supplyTracker,
                    $parameters
                );

                $childNode['bom_qty_per_unit'] = $item->quantity;
                $childNode['scrap_percentage'] = $scrapPct;

                $node['children'][] = $childNode;
            }
        } else {
            // Direct Purchase item or leaf Raw Material item with no further BOM explosion
            $pId = $product->id;
            $unitCost = (float) ($product->unit_cost ?? $product->cost_price ?? 0.0);

            $prApproved = $this->getPrApprovedQty($tenantId, $product->id, $warehouseId);
            $prDraft = $this->getPrDraftQty($tenantId, $product->id, $warehouseId);
            $unprocuredShortage = max(0.0, $netShortage - $prApproved);

            if (isset($consolidatedShortages[$pId])) {
                $consolidatedShortages[$pId]['required_qty'] += $requiredQty;
                $consolidatedShortages[$pId]['gross_shortage_qty'] += $netShortage;
                $consolidatedShortages[$pId]['open_po_supply_qty'] += $openPoQty;
                $consolidatedShortages[$pId]['open_wo_supply_qty'] += $openWoQty;
                $consolidatedShortages[$pId]['total_usable_supply_qty'] += ($openPoQty + $openWoQty);
                $consolidatedShortages[$pId]['net_shortage_qty'] = max(0.0, $consolidatedShortages[$pId]['gross_shortage_qty'] - $prApproved);
                $consolidatedShortages[$pId]['net_requirement_qty'] = $consolidatedShortages[$pId]['net_shortage_qty'];

                $lsResult = $lotSizer->calculatePlannedSupply($consolidatedShortages[$pId]['net_shortage_qty'], $moq, $orderMultiple);
                $consolidatedShortages[$pId]['planned_supply_qty'] = $lsResult['planned_supply_qty'];
                $consolidatedShortages[$pId]['lot_sizing_rule'] = $lsResult['rule'];
                $consolidatedShortages[$pId]['suggested_pr_qty'] = $lsResult['planned_supply_qty'];
                $consolidatedShortages[$pId]['total_cost'] = $lsResult['planned_supply_qty'] * $unitCost;
                if ($sourceRef && !in_array($sourceRef, $consolidatedShortages[$pId]['sources'])) {
                    $consolidatedShortages[$pId]['sources'][] = $sourceRef;
                }
            } elseif ($netShortage > 0) {
                $lsResult = $lotSizer->calculatePlannedSupply($unprocuredShortage, $moq, $orderMultiple);
                $consolidatedShortages[$pId] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'type' => $product->type ?? 'raw_material',
                    'uom_code' => $product->uom?->code ?? 'PCS',
                    'on_hand_qty' => $onHand,
                    'reserved_qty' => $reserved,
                    'available_qty' => $totalAvailableStock,
                    'open_po_supply_qty' => $openPoQty,
                    'open_wo_supply_qty' => $openWoQty,
                    'total_usable_supply_qty' => $usableSupply,
                    'warehouse_breakdown' => $stockSnapshot['breakdown'] ?? [],
                    'required_qty' => $requiredQty,
                    'required_date' => $requiredDate ? $requiredDate->toDateString() : null,
                    'gross_shortage_qty' => $netShortage,
                    'pr_approved_qty' => $prApproved,
                    'pr_draft_qty' => $prDraft,
                    'net_shortage_qty' => $unprocuredShortage,
                    'net_requirement_qty' => $unprocuredShortage,
                    'minimum_order_qty' => $moq,
                    'order_multiple' => $orderMultiple,
                    'planned_supply_qty' => $lsResult['planned_supply_qty'],
                    'lot_sizing_rule' => $lsResult['rule'],
                    'suggested_pr_qty' => $lsResult['planned_supply_qty'],
                    'unit_cost' => $unitCost,
                    'total_cost' => $lsResult['planned_supply_qty'] * $unitCost,
                    'preferred_vendor_id' => $product->preferred_vendor_id,
                    'preferred_vendor_name' => $product->vendor ? $product->vendor->name : null,
                    'sources' => $sourceRef ? [$sourceRef] : [],
                ];
            }
        }

        return $node;
    }

    /**
     * Get usable open Purchase Order supply arriving on or before requiredDate.
     */
    public function getOpenPoSupplySnapshot(
        int $tenantId,
        int $productId,
        ?int $warehouseId = null,
        ?\Illuminate\Support\Carbon $requiredDate = null,
        array $supplyTracker = []
    ): array {
        $query = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.tenant_id', $tenantId)
            ->where('purchase_order_items.product_id', $productId)
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn(DB::raw('LOWER(purchase_orders.status)'), [
                'approved', 'issued', 'partially received', 'partially_received', 'partially received'
            ]);

        if ($warehouseId) {
            $query->where(function ($q) use ($warehouseId) {
                $q->where('purchase_orders.location', (string) $warehouseId)
                  ->orWhereNull('purchase_orders.location');
            });
        }

        if ($requiredDate) {
            $targetDate = $requiredDate->copy()->endOfDay()->toDateTimeString();
            $query->where(function ($q) use ($targetDate) {
                $q->where('purchase_orders.delivery_date', '<=', $targetDate)
                  ->orWhere(function ($q2) use ($targetDate) {
                      $q2->whereNull('purchase_orders.delivery_date')
                         ->where('purchase_orders.date', '<=', $targetDate);
                  });
            });
        }

        $poItems = $query->select(
            'purchase_order_items.id',
            'purchase_order_items.quantity',
            'purchase_order_items.received_qty',
            'purchase_orders.delivery_date',
            'purchase_orders.date'
        )->get();

        $usableQty = 0.0;
        $itemsData = [];

        foreach ($poItems as $item) {
            $ordered = (float) $item->quantity;
            $received = (float) ($item->received_qty ?? 0.0);
            $openBalance = max(0.0, $ordered - $received);

            $alreadyConsumed = $supplyTracker['po_items_consumed'][$item->id] ?? 0.0;
            $unconsumed = max(0.0, $openBalance - $alreadyConsumed);

            if ($unconsumed > 0) {
                $usableQty += $unconsumed;
                $itemsData[] = [
                    'id' => $item->id,
                    'unconsumed_qty' => $unconsumed,
                    'open_balance' => $openBalance,
                    'date' => $item->delivery_date ?? $item->date,
                ];
            }
        }

        return [
            'usable_qty' => $usableQty,
            'items' => $itemsData,
        ];
    }

    /**
     * Get usable open Production Order supply completing on or before requiredDate.
     */
    public function getOpenProductionOrderSupplySnapshot(
        int $tenantId,
        int $productId,
        ?int $warehouseId = null,
        ?\Illuminate\Support\Carbon $requiredDate = null,
        array $supplyTracker = []
    ): array {
        $query = DB::table('production_orders')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('LOWER(status)'), ['released', 'in_progress']);

        if ($requiredDate) {
            $targetDate = $requiredDate->copy()->endOfDay()->toDateTimeString();
            $query->where(function ($q) use ($targetDate) {
                $q->where('end_date', '<=', $targetDate)
                  ->orWhere(function ($q2) use ($targetDate) {
                      $q2->whereNull('end_date')
                         ->where('start_date', '<=', $targetDate);
                  });
            });
        }

        $orders = $query->select(
            'id',
            'quantity_ordered',
            'quantity_produced',
            'start_date',
            'end_date'
        )->get();

        $usableQty = 0.0;
        $ordersData = [];

        foreach ($orders as $wo) {
            $ordered = (float) $wo->quantity_ordered;
            $produced = (float) ($wo->quantity_produced ?? 0.0);
            $openBalance = max(0.0, $ordered - $produced);

            $alreadyConsumed = $supplyTracker['wo_orders_consumed'][$wo->id] ?? 0.0;
            $unconsumed = max(0.0, $openBalance - $alreadyConsumed);

            if ($unconsumed > 0) {
                $usableQty += $unconsumed;
                $ordersData[] = [
                    'id' => $wo->id,
                    'unconsumed_qty' => $unconsumed,
                    'open_balance' => $openBalance,
                    'date' => $wo->end_date ?? $wo->start_date,
                ];
            }
        }

        return [
            'usable_qty' => $usableQty,
            'orders' => $ordersData,
        ];
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

            $maxNum = PurchaseRequisition::withoutGlobalScopes()
                ->where('requisition_number', 'like', "{$prefix}%")
                ->get()
                ->map(function ($pr) use ($prefix) {
                    $numStr = str_replace($prefix, '', $pr->requisition_number);
                    return intval($numStr);
                })
                ->max() ?? 0;

            $nextNum = $maxNum + 1;
            do {
                $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                $exists = PurchaseRequisition::withoutGlobalScopes()->where('requisition_number', $requisitionNumber)->exists();
                if ($exists) {
                    $nextNum++;
                }
            } while ($exists);

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
