<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Repositories\SalesOrderRepository;
use App\Domains\Inventory\Models\Product;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class SalesOrderService
{
    public function __construct(
        private readonly SalesOrderRepository $salesOrders,
    ) {
    }

    public function latest(): Collection
    {
        return $this->salesOrders->latest();
    }

    public function find(int $id): ?SalesOrder
    {
        return $this->salesOrders->find($id);
    }

    public function getNextSalesOrderNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "{$year}-";

        $latest = SalesOrder::query()
            ->where('sales_order_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($latest) {
            $rawNum = $latest->getRawOriginal('sales_order_number');
            $lastNumStr = str_replace($prefix, '', $rawNum);
            $nextNum = intval($lastNumStr) + 1;
        }

        return 'SO-' . $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['sales_order_number'])) {
                $data['sales_order_number'] = $this->getNextSalesOrderNumber();
            }

            $discountType = $data['discount_type'] ?? 'item_wise';
            $taxType = $data['tax_type'] ?? 'item_wise_tax';
            $orderTaxRate = floatval($data['order_tax_rate'] ?? 0);

            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;
                $discount = ($discountType === 'item_wise') ? floatval($item['discount'] ?? 0) : 0;
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $amount = ($qty * $price) - $discount;
                $itemTax = ($taxType === 'item_wise_tax') ? ($amount * ($taxRate / 100)) : 0;

                $subtotal += ($qty * $price);
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = Product::find($productId);
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

            $discountVal = 0;
            if ($discountType === 'item_wise') {
                foreach ($items as $it) {
                    $discountVal += floatval($it['discount'] ?? 0);
                }
            } elseif ($discountType === 'order_wise') {
                $discountVal = floatval($data['discount'] ?? 0);
            }

            if ($taxType === 'order_wise_tax') {
                $taxableAmount = max(0, $subtotal - $discountVal);
                $tax = $taxableAmount * ($orderTaxRate / 100);
            } elseif ($taxType === 'without_tax') {
                $tax = 0;
            }

            $freightTerms = $data['freight_terms'] ?? 'To Pay';
            $freightAmount = floatval($data['freight_amount'] ?? $data['shipping_charges'] ?? 0);
            $adjustment = floatval($data['adjustment'] ?? 0);

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $totalTax = $tax;

            $taxableSubtotal = max(0, $subtotal - $discountVal);
            $totalAmount = $taxableSubtotal + $totalTax + $effectiveFreight + $adjustment;

            $data['freight_terms'] = $freightTerms;
            $data['freight_amount'] = $freightAmount;
            $data['shipping_charges'] = $freightAmount;
            $data['discount'] = $discountVal;
            $data['subtotal'] = $subtotal;
            $data['tax'] = $totalTax;
            $data['total_amount'] = max(0, $totalAmount);

            $salesOrder = $this->salesOrders->create($data);
            $salesOrder->items()->createMany($itemsData);

            if (!empty($data['quotation_id'])) {
                $quotation = Quotation::find($data['quotation_id']);
                if ($quotation) {
                    $quotation->update(['status' => 'Converted']);
                }
            }

            return $salesOrder;
        });
    }

    public function update(SalesOrder $salesOrder, array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $data, $items) {
            $discountType = $data['discount_type'] ?? $salesOrder->discount_type ?? 'item_wise';
            $taxType = $data['tax_type'] ?? $salesOrder->tax_type ?? 'item_wise_tax';
            $orderTaxRate = floatval($data['order_tax_rate'] ?? $salesOrder->order_tax_rate ?? 0);

            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;
                $discount = ($discountType === 'item_wise') ? floatval($item['discount'] ?? 0) : 0;
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $amount = ($qty * $price) - $discount;
                $itemTax = ($taxType === 'item_wise_tax') ? ($amount * ($taxRate / 100)) : 0;

                $subtotal += ($qty * $price);
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = Product::find($productId);
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

            $discountVal = 0;
            if ($discountType === 'item_wise') {
                foreach ($items as $it) {
                    $discountVal += floatval($it['discount'] ?? 0);
                }
            } elseif ($discountType === 'order_wise') {
                $discountVal = floatval($data['discount'] ?? 0);
            }

            if ($taxType === 'order_wise_tax') {
                $taxableAmount = max(0, $subtotal - $discountVal);
                $tax = $taxableAmount * ($orderTaxRate / 100);
            } elseif ($taxType === 'without_tax') {
                $tax = 0;
            }

            $freightTerms = $data['freight_terms'] ?? $salesOrder->freight_terms ?? 'To Pay';
            $freightAmount = floatval($data['freight_amount'] ?? $data['shipping_charges'] ?? 0);
            $adjustment = floatval($data['adjustment'] ?? 0);

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $totalTax = $tax;

            $taxableSubtotal = max(0, $subtotal - $discountVal);
            $totalAmount = $taxableSubtotal + $totalTax + $effectiveFreight + $adjustment;

            $data['freight_terms'] = $freightTerms;
            $data['freight_amount'] = $freightAmount;
            $data['shipping_charges'] = $freightAmount;
            $data['discount'] = $discountVal;
            $data['subtotal'] = $subtotal;
            $data['tax'] = $totalTax;
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
