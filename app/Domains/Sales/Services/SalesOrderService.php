<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Repositories\SalesOrderRepository;
use App\Domains\Inventory\Models\Product;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
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
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $year = now()->format('Y');
        $prefix = "SO-{$year}-";

        $latest = SalesOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('sales_order_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($latest) {
            $rawNum = $latest->getRawOriginal('sales_order_number');
            $lastNumStr = str_replace($prefix, '', $rawNum);
            $nextNum = intval($lastNumStr) + 1;
        }

        $nextSoNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        while (SalesOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sales_order_number', $nextSoNumber)->exists()) {
            $nextNum++;
            $nextSoNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        }

        return $nextSoNumber;
    }

    public function create(array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['sales_order_number'])) {
                $data['sales_order_number'] = $this->getNextSalesOrderNumber();
            }

            $taxMode = $data['tax_mode'] ?? 'exclusive';
            $discountType = $data['discount_type'] ?? 'item_wise';
            $taxType = $data['tax_type'] ?? 'item_wise_tax';
            $orderTaxRate = floatval($data['order_tax_rate'] ?? 0);

            $subtotal = 0;
            $rawLines = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = convert_to_base((float)($item['unit_price'] ?? 0));
                $taxRate = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;
                $lineDiscount = ($discountType === 'item_wise') ? convert_to_base((float)($item['discount'] ?? 0)) : 0;
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $lineGross = $qty * $price;
                $subtotal += $lineGross;

                $rawLines[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item' => $item,
                    'qty' => $qty,
                    'price' => $price,
                    'tax_rate' => $taxRate,
                    'line_discount' => $lineDiscount,
                    'line_gross' => $lineGross,
                ];
            }

            $discountVal = 0;
            if ($discountType === 'item_wise') {
                foreach ($rawLines as $rl) {
                    $discountVal += floatval($rl['line_discount'] ?? 0);
                }
            } elseif ($discountType === 'order_wise') {
                $discountVal = convert_to_base((float)($data['discount'] ?? 0));
            }

            $tax = 0;
            $itemsData = [];

            foreach ($rawLines as $rl) {
                $lineGross = $rl['line_gross'];
                $taxRate = $rl['tax_rate'];
                $qty = $rl['qty'];
                $price = $rl['price'];
                $productId = $rl['product_id'];
                $warehouseId = $rl['warehouse_id'];
                $item = $rl['item'];

                $lineDisc = ($discountType === 'item_wise') 
                    ? $rl['line_discount'] 
                    : (($subtotal > 0 && $discountVal > 0) ? ($lineGross / $subtotal) * $discountVal : 0);

                if ($taxMode === 'inclusive') {
                    $lineNet = max(0, $lineGross - $lineDisc);
                    $lineBase = ($taxRate > 0 && $taxType === 'item_wise_tax') ? round($lineNet / (1 + ($taxRate / 100)), 4) : $lineNet;
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineNet - $lineBase) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                } else {
                    $lineTaxable = max(0, $lineGross - $lineDisc);
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineTaxable * ($taxRate / 100)) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                }

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
                    'discount' => ($discountType === 'item_wise') ? $rl['line_discount'] : 0,
                    'amount' => $amount,
                ];
            }

            if ($taxType === 'order_wise_tax') {
                $taxableAmount = max(0, $subtotal - $discountVal);
                if ($taxMode === 'inclusive') {
                    $tax = ($orderTaxRate > 0) ? ($taxableAmount - ($taxableAmount / (1 + ($orderTaxRate / 100)))) : 0;
                } else {
                    $tax = $taxableAmount * ($orderTaxRate / 100);
                }
            } elseif ($taxType === 'without_tax') {
                $tax = 0;
            }

            $tax = round($tax, 2);

            $freightTerms = $data['freight_terms'] ?? 'To Pay';
            $freightAmount = convert_to_base((float)($data['freight_amount'] ?? $data['shipping_charges'] ?? 0));
            $adjustment = convert_to_base((float)($data['adjustment'] ?? 0));

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $totalTax = $tax;

            if ($taxMode === 'inclusive') {
                $totalAmount = max(0, $subtotal - $discountVal) + $effectiveFreight + $adjustment;
            } else {
                $taxableSubtotal = max(0, $subtotal - $discountVal);
                $totalAmount = $taxableSubtotal + $totalTax + $effectiveFreight + $adjustment;
            }

            $data['tax_mode'] = $taxMode;
            $data['freight_terms'] = $freightTerms;
            $data['freight_amount'] = $freightAmount;
            $data['shipping_charges'] = $freightAmount;
            $data['discount'] = $discountVal;
            $data['subtotal'] = $subtotal;
            $data['tax'] = $totalTax;
            $data['adjustment'] = $adjustment;
            $data['total_amount'] = max(0, $totalAmount);

            $salesOrder = $this->salesOrders->create($data);
            $salesOrder->items()->createMany($itemsData);

            if (!empty($data['quotation_id'])) {
                $quotation = Quotation::find($data['quotation_id']);
                if ($quotation) {
                    $quotation->update(['status' => 'Converted']);
                    if ($quotation->lead_id) {
                        $lead = Lead::find($quotation->lead_id);
                        if ($lead) {
                            $lead->update([
                                'status' => 'Converted',
                                'is_customer' => true,
                                'converted_at' => now(),
                            ]);
                        }
                    }
                    if ($quotation->crm_deal_id) {
                        $deal = CrmDeal::find($quotation->crm_deal_id);
                        if ($deal) {
                            $deal->update([
                                'stage' => 'Won',
                                'probability' => 100,
                                'estimated_value' => $salesOrder->total_amount,
                            ]);
                        }
                    }
                }
            }

            return $salesOrder;
        });
    }

    public function update(SalesOrder $salesOrder, array $data, array $items): SalesOrder
    {
        if ($salesOrder->status !== 'Draft') {
            throw new \Exception('Only Draft Sales Orders can be updated.');
        }

        return DB::transaction(function () use ($salesOrder, $data, $items) {
            $taxMode = $data['tax_mode'] ?? $salesOrder->tax_mode ?? 'exclusive';
            $discountType = $data['discount_type'] ?? $salesOrder->discount_type ?? 'item_wise';
            $taxType = $data['tax_type'] ?? $salesOrder->tax_type ?? 'item_wise_tax';
            $orderTaxRate = floatval($data['order_tax_rate'] ?? $salesOrder->order_tax_rate ?? 0);

            $subtotal = 0;
            $rawLines = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = convert_to_base((float)($item['unit_price'] ?? 0));
                $taxRate = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;
                $lineDiscount = ($discountType === 'item_wise') ? convert_to_base((float)($item['discount'] ?? 0)) : 0;
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $lineGross = $qty * $price;
                $subtotal += $lineGross;

                $rawLines[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item' => $item,
                    'qty' => $qty,
                    'price' => $price,
                    'tax_rate' => $taxRate,
                    'line_discount' => $lineDiscount,
                    'line_gross' => $lineGross,
                ];
            }

            $discountVal = 0;
            if ($discountType === 'item_wise') {
                foreach ($rawLines as $rl) {
                    $discountVal += floatval($rl['line_discount'] ?? 0);
                }
            } elseif ($discountType === 'order_wise') {
                $discountVal = convert_to_base((float)($data['discount'] ?? 0));
            }

            $tax = 0;
            $itemsData = [];

            foreach ($rawLines as $rl) {
                $lineGross = $rl['line_gross'];
                $taxRate = $rl['tax_rate'];
                $qty = $rl['qty'];
                $price = $rl['price'];
                $productId = $rl['product_id'];
                $warehouseId = $rl['warehouse_id'];
                $item = $rl['item'];

                $lineDisc = ($discountType === 'item_wise') 
                    ? $rl['line_discount'] 
                    : (($subtotal > 0 && $discountVal > 0) ? ($lineGross / $subtotal) * $discountVal : 0);

                if ($taxMode === 'inclusive') {
                    $lineNet = max(0, $lineGross - $lineDisc);
                    $lineBase = ($taxRate > 0 && $taxType === 'item_wise_tax') ? round($lineNet / (1 + ($taxRate / 100)), 4) : $lineNet;
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineNet - $lineBase) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                } else {
                    $lineTaxable = max(0, $lineGross - $lineDisc);
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineTaxable * ($taxRate / 100)) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                }

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
                    'discount' => ($discountType === 'item_wise') ? $rl['line_discount'] : 0,
                    'amount' => $amount,
                ];
            }

            if ($taxType === 'order_wise_tax') {
                $taxableAmount = max(0, $subtotal - $discountVal);
                if ($taxMode === 'inclusive') {
                    $tax = ($orderTaxRate > 0) ? ($taxableAmount - ($taxableAmount / (1 + ($orderTaxRate / 100)))) : 0;
                } else {
                    $tax = $taxableAmount * ($orderTaxRate / 100);
                }
            } elseif ($taxType === 'without_tax') {
                $tax = 0;
            }

            $tax = round($tax, 2);

            $freightTerms = $data['freight_terms'] ?? $salesOrder->freight_terms ?? 'To Pay';
            $freightAmount = convert_to_base((float)($data['freight_amount'] ?? $data['shipping_charges'] ?? 0));
            $adjustment = convert_to_base((float)($data['adjustment'] ?? 0));

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $totalTax = $tax;

            if ($taxMode === 'inclusive') {
                $totalAmount = max(0, $subtotal - $discountVal) + $effectiveFreight + $adjustment;
            } else {
                $taxableSubtotal = max(0, $subtotal - $discountVal);
                $totalAmount = $taxableSubtotal + $totalTax + $effectiveFreight + $adjustment;
            }

            $data['tax_mode'] = $taxMode;
            $data['freight_terms'] = $freightTerms;
            $data['freight_amount'] = $freightAmount;
            $data['shipping_charges'] = $freightAmount;
            $data['discount'] = $discountVal;
            $data['subtotal'] = $subtotal;
            $data['tax'] = $totalTax;
            $data['adjustment'] = $adjustment;
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
