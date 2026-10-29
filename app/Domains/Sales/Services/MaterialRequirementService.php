<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\DispatchOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Repositories\MaterialRequirementRepository;
use Illuminate\Support\Facades\DB;

class MaterialRequirementService
{
    public function __construct(
        private readonly MaterialRequirementRepository $requirementRepo
    ) {}

    public function getNextRequirementNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "MR-{$year}-";

        $latest = MaterialRequirement::where('requirement_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($latest) {
            $lastNumStr = str_replace($prefix, '', $latest->requirement_number);
            $nextNum = intval($lastNumStr) + 1;
        }

        do {
            $candidate = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            $nextNum++;
        } while (MaterialRequirement::where('requirement_number', $candidate)->exists());

        return $candidate;
    }

    public function create(array $data, array $items): MaterialRequirement
    {
        return DB::transaction(function () use ($data, $items) {
            $data['tenant_id'] = tenant_id() ?? 1;
            $data['status'] = $data['status'] ?? 'Pending';

            $delivery = $this->requirementRepo->create($data);

            foreach ($items as $itemData) {
                if (empty($itemData['quantity']) || floatval($itemData['quantity']) <= 0) {
                    continue;
                }

                $product = \App\Domains\Inventory\Models\Product::find($itemData['product_id']);
                if ($product && $product->isService()) {
                    continue;
                }

                MaterialRequirementItem::create([
                    'tenant_id'               => $delivery->tenant_id,
                    'material_requirement_id' => $delivery->id,
                    'sales_order_item_id'     => $itemData['sales_order_item_id'] ?? null,
                    'product_id'              => $itemData['product_id'],
                    'warehouse_id'            => $itemData['warehouse_id'] ?? null,
                    'quantity'                => $itemData['quantity'],
                    'quantity_ordered'        => $itemData['quantity'],
                    'quantity_reserved'       => 0.0000,
                    'status'                  => 'Pending',
                ]);
            }

            return $delivery;
        });
    }

    public function reserveQty(MaterialRequirementItem $item, float $qtyToReserve): void
    {
        $warehouseId = $item->warehouse_id;
        $productId = $item->product_id;
        $tenantId = $item->materialRequirement->tenant_id;

        $availableStock = StockService::getAvailableStock($productId, $warehouseId);

        if ($qtyToReserve > $availableStock) {
            throw new \InvalidArgumentException("Cannot reserve {$qtyToReserve}. Only {$availableStock} is available in this warehouse.");
        }

        StockService::reserveStock(
            $tenantId,
            $productId,
            $warehouseId,
            $qtyToReserve,
            'DeliveryOrder',
            $item->material_requirement_id,
            $item->id
        );

        $item->increment('quantity_reserved', $qtyToReserve);

        if ($item->quantity_reserved >= $item->quantity_ordered) {
            $item->update(['status' => 'Reserved']);
        } else {
            $item->update(['status' => 'Partially Reserved']);
        }

        $this->updateOverallDeliveryStatus($item->materialRequirement);
    }

    public function createPurchaseRequisition(MaterialRequirementItem $item, float $qtyToRequest, ?int $warehouseId, ?string $notes, ?string $expectedDate = null): PurchaseRequisition
    {
        $tenantId = require_tenant_id();

        return DB::transaction(function () use ($item, $qtyToRequest, $warehouseId, $notes, $expectedDate, $tenantId) {
            $companyId = company_id() ?? auth()->user()?->company_id;
            $branchId = branch_id() ?? auth()->user()?->branch_id;

            $year = now()->format('Y');
            $prefix = "PR-{$year}-";
            $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastPr) {
                $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            while (PurchaseRequisition::where('requisition_number', $requisitionNumber)->exists()) {
                $nextNum++;
                $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            }

            $pr = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'requisition_number' => $requisitionNumber,
                'requisition_date' => now()->toDateString(),
                'expected_date' => $expectedDate ? date('Y-m-d', strtotime($expectedDate)) : null,
                'status' => 'Draft',
                'source_type' => 'material_requirement',
                'source_id' => $item->material_requirement_id,
                'notes' => $notes ?: 'Generated from Material Requirement #' . $item->materialRequirement->requirement_number,
                'requested_by' => auth()->id() ?: 1,
            ]);

            PurchaseRequisitionItem::create([
                'purchase_requisition_id' => $pr->id,
                'product_id' => $item->product_id,
                'quantity' => $qtyToRequest,
                'warehouse_id' => $warehouseId ?: $item->warehouse_id,
                'estimated_cost' => $item->product->unit_cost ?? 0.00,
            ]);

            $newPrRaised = (float)($item->quantity_pr_raised ?? 0) + $qtyToRequest;
            $orderedQty = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
            $reservedQty = (float)$item->quantity_reserved;
            $pendingQty = max(0, $orderedQty - $reservedQty);

            $newStatus = $newPrRaised >= $pendingQty ? 'Waiting Purchase' : 'Partially PR Raised';

            $item->update([
                'quantity_pr_raised' => $newPrRaised,
                'status' => $newStatus,
            ]);
            $this->updateOverallDeliveryStatus($item->materialRequirement);

            return $pr;
        });
    }

    public function createProductionRequest(MaterialRequirementItem $item, float $qtyToMfg, ?string $notes): ProductionOrderRequest
    {
        $tenantId = require_tenant_id();

        return DB::transaction(function () use ($item, $qtyToMfg, $notes, $tenantId) {
            $existingRequest = ProductionOrderRequest::where('tenant_id', $tenantId)
                ->where('material_requirement_item_id', $item->id)
                ->whereNotIn('status', ['rejected', 'completed', 'cancelled'])
                ->lockForUpdate()
                ->first();

            if ($existingRequest) {
                throw new \InvalidArgumentException('A production request already exists for this material requirement line.');
            }

            $req = ProductionOrderRequest::create([
                'tenant_id' => $tenantId,
                'material_requirement_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity_requested' => $qtyToMfg,
                'status' => 'draft',
                'notes' => $notes ?? "Requested from MR {$item->materialRequirement->requirement_number}",
                'created_by' => auth()->id(),
            ]);

            $item->update(['status' => 'Waiting Production']);
            $this->updateOverallDeliveryStatus($item->materialRequirement);

            return $req;
        });
    }

    public function startPicking(MaterialRequirement $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $delivery->update(['status' => 'Picked']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Picked']);
            }
        });
    }

    public function pack(MaterialRequirement $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $delivery->update(['status' => 'Packed']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Packed']);
            }
        });
    }

    public function dispatch(MaterialRequirement $delivery, array $data): void
    {
        DB::transaction(function () use ($delivery, $data) {
            $delivery->update([
                'status' => 'Dispatched',
                'carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($delivery->items as $doItem) {
                $doItem->update(['status' => 'Dispatched']);

                if (!$doItem->product_id || $doItem->product?->type === 'Service') {
                    continue;
                }

                $tenantId = $delivery->tenant_id;
                $productId = $doItem->product_id;
                $warehouseId = $doItem->warehouse_id;
                $qty = (float) $doItem->quantity_ordered;

                StockService::releaseStock($tenantId, $productId, $warehouseId, $qty, 'DeliveryOrder', $delivery->id, $doItem->id);
                StockService::recordOutflow($tenantId, $productId, $warehouseId, $qty, 'DeliveryOrder', $delivery->id, []);
            }

            $delivery->salesOrder->update(['status' => 'Shipped']);
        });
    }

    public function deliver(MaterialRequirement $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $delivery->update(['status' => 'Delivered']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Delivered']);
            }
            $delivery->salesOrder->update(['status' => 'Delivered']);
        });
    }

    public function ship(MaterialRequirement $delivery, array $allocations): void
    {
        DB::transaction(function () use ($delivery, $allocations) {
            $delivery->update(['status' => 'Shipped']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Shipped']);
            }
            $delivery->salesOrder->update(['status' => 'Shipped']);
        });
    }

    public function releaseForDispatch(MaterialRequirement $delivery, array $selectedItemIds = [], array $itemWarehouses = [], array $releaseQuantities = []): array
    {
        return DB::transaction(function () use ($delivery, $selectedItemIds, $itemWarehouses, $releaseQuantities) {
            // 1. Update warehouse assignment on items if submitted
            if (!empty($itemWarehouses)) {
                foreach ($itemWarehouses as $itemId => $whId) {
                    if ($whId) {
                        MaterialRequirementItem::where('id', $itemId)
                            ->where('material_requirement_id', $delivery->id)
                            ->update(['warehouse_id' => (int) $whId]);
                    }
                }
            }

            $delivery->load(['items.product', 'items.warehouse']);

            $allPhysicalItems = $delivery->items->filter(fn ($i) => !($i->product && $i->product->isService()));
            if ($allPhysicalItems->isEmpty()) {
                $allPhysicalItems = $delivery->items;
            }

            if (empty($selectedItemIds)) {
                $itemsToRelease = $allPhysicalItems;
            } else {
                $selectedIds = array_map('intval', (array) $selectedItemIds);
                $itemsToRelease = $allPhysicalItems->filter(fn ($i) => in_array($i->id, $selectedIds));
            }

            if ($itemsToRelease->isEmpty()) {
                throw new \Exception("Please select at least one item with unreleased quantity to release for dispatch.");
            }

            // 2. Validate stock and release quantities for selected items
            $defaultWarehouseId = Warehouse::where('tenant_id', $delivery->tenant_id)->orderBy('is_default', 'desc')->first()?->id ?? 1;
            $releasedCount = 0;

            foreach ($itemsToRelease as $item) {
                if ($item->product && $item->product->isService()) {
                    continue;
                }
                $whId = $item->warehouse_id ?: $defaultWarehouseId;
                $avail = StockService::getAvailableStock($item->product_id, $whId);
                $reserved = (float) ($item->quantity_reserved ?? 0);
                $ordered = (float) ($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                $prevReleased = (float) ($item->quantity_released ?? 0);
                $remainingToRelease = max(0.0, $ordered - $prevReleased);

                if ($remainingToRelease <= 0) {
                    continue; // Already fully released, skip
                }

                // If specific release quantity is requested for this item
                $requestedReleaseQty = isset($releaseQuantities[$item->id]) ? (float)$releaseQuantities[$item->id] : $remainingToRelease;
                $qtyToRelease = min($remainingToRelease, max(0.0, $requestedReleaseQty));

                if ($qtyToRelease <= 0) {
                    continue;
                }

                if (($avail + $reserved) < $qtyToRelease) {
                    $whName = $item->warehouse?->name ?? 'selected warehouse';
                    $totalAvail = $avail + $reserved;
                    throw new \Exception("Cannot release {$qtyToRelease} units of '{$item->product?->name}': Only {$totalAvail} units available in {$whName}.");
                }

                $newTotalReleased = $prevReleased + $qtyToRelease;
                $itemStatus = ($newTotalReleased >= $ordered) ? 'Released' : 'Partially Released';

                $item->update([
                    'quantity_released' => $newTotalReleased,
                    'status'            => $itemStatus,
                ]);
                $releasedCount++;
            }

            if ($releasedCount === 0) {
                throw new \Exception("All selected items are already fully released or have 0 quantity to release.");
            }

            // 3. Update overall MR status based on physical items
            $delivery->load('items.product');
            $this->updateOverallDeliveryStatus($delivery);

            $allPhysical = $delivery->items->filter(fn ($i) => !($i->product && $i->product->isService()));
            $fullyReleasedCount = $allPhysical->filter(function ($i) {
                $ordered = (float) ($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity);
                return (float) ($i->quantity_released ?? 0) >= $ordered;
            })->count();

            return [
                'released_count'       => $releasedCount,
                'fully_released_count' => $fullyReleasedCount,
                'total_count'          => $allPhysical->count(),
                'status'               => $delivery->fresh()->status,
            ];
        });
    }

    public function cancel(MaterialRequirement $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $delivery->update(['status' => 'Cancelled']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Cancelled']);
            }
        });
    }

    public function storeDispatchOrder(MaterialRequirement $delivery, array $data): DispatchOrder
    {
        return DB::transaction(function () use ($delivery, $data) {
            $year = now()->format('Y');
            $prefix = "DSP-{$year}-";

            $latest = DispatchOrder::where('tenant_id', $delivery->tenant_id)
                ->where('dispatch_number', 'like', "{$prefix}%")
                ->orderByDesc('id')
                ->first();

            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->dispatch_number);
                $nextNum = intval($lastNumStr) + 1;
            }
            $dispatchNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $dispatchOrder = DispatchOrder::create([
                'tenant_id' => $delivery->tenant_id,
                'material_requirement_id' => $delivery->id,
                'sales_order_id' => $delivery->sales_order_id,
                'dispatch_number' => $dispatchNumber,
                'dispatch_date' => now()->toDateString(),
                'carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_phone' => $data['driver_phone'] ?? null,
                'status' => 'Pending',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($delivery->items as $doItem) {
                $orderedQty = (float) ($doItem->quantity_ordered > 0 ? $doItem->quantity_ordered : $doItem->quantity);
                $reservedQty = (float) $doItem->quantity_reserved;
                $dispatchQty = $reservedQty > 0 ? $reservedQty : $orderedQty;

                if ($dispatchQty <= 0) continue;

                DispatchOrderItem::create([
                    'dispatch_order_id' => $dispatchOrder->id,
                    'material_requirement_item_id' => $doItem->id,
                    'product_id' => $doItem->product_id,
                    'warehouse_id' => $doItem->warehouse_id,
                    'quantity_ordered' => $orderedQty,
                    'quantity_dispatched' => $dispatchQty,
                ]);
            }

            return $dispatchOrder;
        });
    }

    public function updateOverallDeliveryStatus(MaterialRequirement $delivery): void
    {
        if ($delivery->status === 'Cancelled') {
            return;
        }

        $delivery->loadMissing(['items.product', 'salesOrder']);
        $items = $delivery->items;
        if ($items->isEmpty()) return;

        $physicalItems = $items->filter(fn($i) => !($i->product && $i->product->isService()));
        if ($physicalItems->isEmpty()) {
            $physicalItems = $items;
        }

        $totalOrdered = (float) $physicalItems->sum(fn($i) => (float)($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity));
        $totalDelivered = (float) $physicalItems->sum(fn($i) => (float)$i->delivered_qty);
        $totalDispatched = (float) $physicalItems->sum(fn($i) => (float)$i->dispatched_qty);
        $totalReleased = (float) $physicalItems->sum(fn($i) => (float)($i->quantity_released ?? 0));
        $totalReserved = (float) $physicalItems->sum(fn($i) => (float)$i->quantity_reserved);

        $hasWaitingPurchase = $physicalItems->contains(fn($i) => in_array($i->status, ['Waiting Purchase', 'Partially PR Raised']));
        $hasWaitingProduction = $physicalItems->contains(fn($i) => $i->status === 'Waiting Production');
        $allPending = $physicalItems->every(fn($i) => in_array($i->status, ['Pending', 'Draft']) && (float)$i->quantity_reserved == 0 && (float)$i->quantity_released == 0 && (float)$i->dispatched_qty == 0 && (float)$i->delivered_qty == 0);

        // Determine Document Status based on fulfillment lifecycle (never regress delivery/dispatch milestones)
        if ($totalOrdered > 0 && $totalDelivered >= $totalOrdered) {
            $status = 'Delivered';
        } elseif ($totalDelivered > 0) {
            $status = 'Partially Delivered';
        } elseif ($totalOrdered > 0 && $totalDispatched >= $totalOrdered) {
            $status = 'Dispatched';
        } elseif ($totalDispatched > 0) {
            $status = 'Partially Dispatched';
        } elseif ($totalOrdered > 0 && $totalReleased >= $totalOrdered) {
            $status = 'Released';
        } elseif ($totalReleased > 0 || $totalReserved > 0) {
            $status = 'Partially Ready';
        } elseif ($hasWaitingPurchase || $hasWaitingProduction) {
            $status = 'Processing';
        } elseif ($allPending) {
            $status = 'Pending';
        } else {
            $status = 'Processing';
        }

        if ($delivery->status !== $status) {
            $delivery->update(['status' => $status]);
        }

        // Also update Sales Order status if applicable
        if ($delivery->sales_order_id) {
            $so = $delivery->salesOrder ?? \App\Domains\Sales\Models\SalesOrder::withoutGlobalScopes()->find($delivery->sales_order_id);
            if ($so) {
                if ($totalOrdered > 0 && $totalDelivered >= $totalOrdered) {
                    $so->update(['status' => 'Delivered']);
                } elseif ($totalDelivered > 0) {
                    $so->update(['status' => 'Partially Delivered']);
                } elseif ($totalOrdered > 0 && $totalDispatched >= $totalOrdered) {
                    $so->update(['status' => 'Shipped']);
                } elseif ($totalDispatched > 0) {
                    $so->update(['status' => 'Partially Shipped']);
                } else {
                    $so->refreshFulfillmentStatus();
                }
            }
        }
    }
}
