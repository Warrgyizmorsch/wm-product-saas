<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Repositories\SalesOrderRepository;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        private readonly SalesOrderRepository $salesOrders,
    ) {
    }

    public function latest(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->salesOrders->latest();
    }

    public function find(int $id): ?SalesOrder
    {
        return $this->salesOrders->find($id);
    }

    public function getNextSalesOrderNumber(): string
    {
        $latest = SalesOrder::query()->latest('id')->first();

        if (!$latest) {
            return 'SO-0001';
        }

        $rawNum = $latest->getRawOriginal('sales_order_number');
        $nextSeq = intval($rawNum) + 1;
        
        return 'SO-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['sales_order_number'])) {
                $data['sales_order_number'] = $this->getNextSalesOrderNumber();
            }

            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $discount = floatval($item['discount'] ?? 0);
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $amount = ($qty * $price) - $discount;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += ($qty * $price);
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = \App\Domains\Inventory\Models\Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'discount' => $discount,
                    'amount' => $amount,
                ];
            }

            $discountVal = floatval($data['discount'] ?? 0);
            $shippingCharges = floatval($data['shipping_charges'] ?? 0);
            $adjustment = floatval($data['adjustment'] ?? 0);
            $totalAmount = $subtotal + $tax - $discountVal + $shippingCharges + $adjustment;

            $data['subtotal'] = $subtotal;
            $data['tax'] = $tax;
            $data['total_amount'] = max(0, $totalAmount);

            $salesOrder = $this->salesOrders->create($data);
            $salesOrder->items()->createMany($itemsData);

            return $salesOrder;
        });
    }

    public function update(SalesOrder $salesOrder, array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $data, $items) {
            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $discount = floatval($item['discount'] ?? 0);
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $amount = ($qty * $price) - $discount;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += ($qty * $price);
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = \App\Domains\Inventory\Models\Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'discount' => $discount,
                    'amount' => $amount,
                ];
            }

            $discountVal = floatval($data['discount'] ?? 0);
            $shippingCharges = floatval($data['shipping_charges'] ?? 0);
            $adjustment = floatval($data['adjustment'] ?? 0);
            $totalAmount = $subtotal + $tax - $discountVal + $shippingCharges + $adjustment;

            $data['subtotal'] = $subtotal;
            $data['tax'] = $tax;
            $data['total_amount'] = max(0, $totalAmount);

            $salesOrder->update($data);
            $salesOrder->items()->delete();
            $salesOrder->items()->createMany($itemsData);

            return $salesOrder;
        });
    }

    public function delete(SalesOrder $salesOrder): bool
    {
        return $salesOrder->delete();
    }
}
