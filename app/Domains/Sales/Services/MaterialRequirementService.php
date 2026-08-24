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

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
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

            $pr = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
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
        $items = $delivery->items()->get();
        if ($items->isEmpty()) return;

        $allDelivered = true;
        $allDispatched = true;
        $allPacked = true;
        $allPicked = true;
        $allReady = true;
        $allPending = true;
        $anyReadyOrReserved = false;

        foreach ($items as $item) {
            $status = $item->status;
            if ($status !== 'Delivered') $allDelivered = false;
            if ($status !== 'Dispatched') $allDispatched = false;
            if ($status !== 'Packed') $allPacked = false;
            if ($status !== 'Picked') $allPicked = false;
            if ($status !== 'Ready' && $status !== 'Reserved') $allReady = false;
            if ($status !== 'Pending') $allPending = false;
            if ($status === 'Reserved' || $status === 'Ready') $anyReadyOrReserved = true;
        }

        if ($allDelivered) $delivery->update(['status' => 'Delivered']);
        elseif ($allDispatched) $delivery->update(['status' => 'Dispatched']);
        elseif ($allPacked) $delivery->update(['status' => 'Packed']);
        elseif ($allPicked) $delivery->update(['status' => 'Picked']);
        elseif ($allReady) $delivery->update(['status' => 'Ready']);
        elseif ($allPending) $delivery->update(['status' => 'Pending']);
        elseif ($anyReadyOrReserved) $delivery->update(['status' => 'Partially Ready']);
        else $delivery->update(['status' => 'Processing']);
    }
}
