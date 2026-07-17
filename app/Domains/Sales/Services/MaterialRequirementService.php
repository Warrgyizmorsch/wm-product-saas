<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Inventory\Models\SerialNumber;
use Illuminate\Support\Facades\DB;

class MaterialRequirementService
{
    public function getNextRequirementNumber(): string
    {
        $latest = MaterialRequirement::query()->latest('id')->first();
        if (!$latest) {
            return 'MR-0001';
        }
        $rawNum = str_replace('MR-', '', $latest->requirement_number);
        $nextSeq = intval($rawNum) + 1;
        return 'MR-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data, array $items): MaterialRequirement
    {
        return DB::transaction(function () use ($data, $items) {
            $salesOrderId = $data['sales_order_id'];
            $salesOrder = SalesOrder::findOrFail($salesOrderId);

            if (empty($data['requirement_number'])) {
                $data['requirement_number'] = $this->getNextRequirementNumber();
            }

            $delivery = MaterialRequirement::create([
                'tenant_id' => $salesOrder->tenant_id,
                'sales_order_id' => $salesOrderId,
                'requirement_number' => $data['requirement_number'],
                'requirement_date' => $data['requirement_date'] ?? date('Y-m-d'),
                'status' => 'Draft',
                'carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $itemId => $itemData) {
                $qtyToShip = floatval($itemData['quantity'] ?? 0);
                if ($qtyToShip <= 0) continue;

                $soItem = SalesOrderItem::findOrFail($itemId);

                // Calculate remaining unshipped qty
                $shippedQty = MaterialRequirementItem::query()
                    ->whereHas('materialRequirement', function($q) {
                        $q->where('status', 'Shipped');
                    })
                    ->where('sales_order_item_id', $soItem->id)
                    ->sum('quantity');

                $remaining = max(0.0, (float)$soItem->quantity - $shippedQty);
                if ($qtyToShip > $remaining) {
                    throw new \Exception("Quantity to ship ({$qtyToShip}) exceeds remaining ordered quantity ({$remaining}) for item: {$soItem->item_name}");
                }

                $warehouseId = $itemData['warehouse_id'] ?? $soItem->warehouse_id;
                if ($warehouseId && $soItem->product_id && $soItem->product && $soItem->product->type !== 'Service') {
                    $available = \App\Domains\Inventory\Services\StockService::getAvailableStock($soItem->product_id, $warehouseId);
                    if ($qtyToShip > $available) {
                        throw new \Exception("Insufficient stock in selected warehouse for item '{$soItem->item_name}'. Available: {$available}, Requested: {$qtyToShip}");
                    }
                }

                MaterialRequirementItem::create([
                    'material_requirement_id' => $delivery->id,
                    'sales_order_item_id' => $soItem->id,
                    'product_id' => $soItem->product_id,
                    'warehouse_id' => $warehouseId,
                    'batch_id' => !empty($itemData['batch_id']) ? intval($itemData['batch_id']) : null,
                    'quantity' => $qtyToShip,
                ]);
            }

            return $delivery;
        });
    }

    public function ship(MaterialRequirement $delivery, array $allocations = []): void
    {
        DB::transaction(function () use ($delivery, $allocations) {
            if ($delivery->status !== 'Draft') {
                throw new \Exception("Only Draft Material Requirements can be shipped.");
            }

            $delivery->update(['status' => 'Shipped']);

            foreach ($delivery->items as $doItem) {
                $soItem = $doItem->salesOrderItem;
                if (!$soItem || !$doItem->product_id) continue;

                // Check if product is of type Goods (needs inventory management)
                if ($doItem->product->type === 'Service') {
                    continue;
                }

                $tenantId = $delivery->tenant_id;
                $productId = $doItem->product_id;
                $warehouseId = $doItem->warehouse_id;
                $qty = (float)$doItem->quantity;

                // 1. Release matching reservation on the corresponding Sales Order line
                StockService::releaseStock(
                    $tenantId,
                    $productId,
                    $warehouseId,
                    $qty,
                    'SalesOrder',
                    $delivery->sales_order_id,
                    $soItem->id
                );

                // 2. Extract selected serials for this item line if applicable
                $serialsList = [];
                if (isset($allocations[$doItem->id]['serials'])) {
                    $serialsList = array_filter(array_map('trim', $allocations[$doItem->id]['serials']));
                }

                // 3. Record Outflow (decreases quantity, creates ledger card, updates batch)
                $transaction = StockService::recordOutflow(
                    $tenantId,
                    $productId,
                    $warehouseId,
                    $qty,
                    'DeliveryOrder', // Keep the stock outflow transaction type/tag context if needed, or change to MaterialRequirement
                    $delivery->id,
                    $serialsList
                );

                // 4. If serial numbers were supplied, link them to this delivery item
                if (!empty($serialsList)) {
                    SerialNumber::query()
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->whereIn('serial_number', $serialsList)
                        ->update([
                            'material_requirement_item_id' => $doItem->id,
                            'status' => 'Sold',
                            'stock_transaction_id_out' => $transaction->id
                        ]);
                }

                // 5. Explicitly assign batch_id if chosen by the user
                if ($doItem->batch_id) {
                    $doItem->update(['batch_id' => $doItem->batch_id]);
                }
            }

            // 5. Update parent Sales Order status
            $this->updateSalesOrderStatus($delivery->salesOrder);
        });
    }

    public function cancel(MaterialRequirement $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            if ($delivery->status !== 'Draft') {
                throw new \Exception("Only Draft Material Requirements can be cancelled.");
            }

            $delivery->update(['status' => 'Cancelled']);
        });
    }

    private function updateSalesOrderStatus(SalesOrder $order): void
    {
        $allGoodsFullyShipped = true;
        $anyShipped = false;

        foreach ($order->items as $soItem) {
            if (!$soItem->product_id || $soItem->product->type === 'Service') continue;

            $shippedQty = MaterialRequirementItem::query()
                ->whereHas('materialRequirement', function($q) {
                    $q->where('status', 'Shipped');
                })
                ->where('sales_order_item_id', $soItem->id)
                ->sum('quantity');

            if ($shippedQty > 0) {
                $anyShipped = true;
            }

            if ($shippedQty < (float)$soItem->quantity) {
                $allGoodsFullyShipped = false;
            }
        }

        if ($allGoodsFullyShipped && $anyShipped) {
            $order->update(['status' => 'Shipped']);
        } elseif ($anyShipped) {
            $order->update(['status' => 'Partially Shipped']);
        }
    }
}
