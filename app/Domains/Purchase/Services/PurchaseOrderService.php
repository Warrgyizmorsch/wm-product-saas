<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        protected PurchaseOrderRepository $orderRepo
    ) {}

    public function storeOrder(array $validated, int $tenantId): PurchaseOrder
    {
        return DB::transaction(function () use ($validated, $tenantId) {
            $poNumber = $this->orderRepo->getNextPoNumber($tenantId);

            $calculatedSubtotal = 0;
            $calculatedItemDiscount = 0;
            $calculatedItemTax = 0;

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? $item['rate'] ?? 0);
                $disc = (float)($item['discount'] ?? $item['discount_amount'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? $item['tax_percent'] ?? 0);

                $lineSubtotal = $qty * $price;
                $calculatedSubtotal += $lineSubtotal;
                $calculatedItemDiscount += $disc;

                $lineTaxable = max(0, $lineSubtotal - $disc);
                $lineTax = $lineTaxable * ($taxRate / 100);
                $calculatedItemTax += $lineTax;
            }

            $discountType = $validated['discount_type'] ?? 'without_discount';
            $taxType = $validated['tax_type'] ?? 'order_wise_tax';
            $gstType = $validated['gst_type'] ?? 'cgst_sgst';

            $subtotal = isset($validated['subtotal']) && (float)$validated['subtotal'] > 0 ? (float)$validated['subtotal'] : $calculatedSubtotal;

            if ($discountType === 'order_wise') {
                $discountAmount = (float)($validated['discount_amount'] ?? 0);
            } elseif ($discountType === 'item_wise') {
                $discountAmount = $calculatedItemDiscount;
            } else {
                $discountAmount = 0;
            }

            $grossTotal = max(0, $subtotal - $discountAmount);

            if ($taxType === 'order_wise_tax') {
                $taxAmount = (float)($validated['tax_amount'] ?? 0);
            } elseif ($taxType === 'item_wise_tax') {
                $taxAmount = $calculatedItemTax;
            } else {
                $taxAmount = 0;
            }

            $cgstAmount = isset($validated['cgst_amount']) ? (float)$validated['cgst_amount'] : 0;
            $sgstAmount = isset($validated['sgst_amount']) ? (float)$validated['sgst_amount'] : 0;
            $igstAmount = isset($validated['igst_amount']) ? (float)$validated['igst_amount'] : 0;

            if ($taxAmount > 0 && ($cgstAmount + $sgstAmount + $igstAmount) <= 0) {
                if ($gstType === 'igst') {
                    $igstAmount = $taxAmount;
                } else {
                    $cgstAmount = round($taxAmount / 2, 2);
                    $sgstAmount = round($taxAmount - $cgstAmount, 2);
                }
            }

            $grandTotal = isset($validated['grand_total']) && (float)$validated['grand_total'] > 0 ? (float)$validated['grand_total'] : ($grossTotal + $taxAmount);

            $po = $this->orderRepo->create([
                'tenant_id' => $tenantId,
                'purchase_order_number' => $poNumber,
                'date' => $validated['date'] ?? $validated['po_date'] ?? now()->toDateString(),
                'delivery_date' => $validated['delivery_date'] ?? $validated['expected_delivery_date'] ?? null,
                'vendor_id' => $validated['vendor_id'],
                'location' => $validated['location'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'supplier_quotation_number' => $validated['supplier_quotation_number'] ?? null,
                'purchase_requisition_id' => $validated['purchase_requisition_id'] ?? null,
                'status' => 'Draft',
                'discount_type' => $discountType,
                'tax_type' => $taxType,
                'gst_type' => $gstType,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'cgst_amount' => $cgstAmount,
                'sgst_amount' => $sgstAmount,
                'igst_amount' => $igstAmount,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? $item['rate'] ?? 0);
                $disc = (float)($item['discount'] ?? $item['discount_amount'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? $item['tax_percent'] ?? 0);

                $lineSubtotal = ($qty * $price) - $disc;
                $lineTax = $lineSubtotal * ($taxRate / 100);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $qty,
                    'rate' => $price,
                    'amount' => $lineSubtotal,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $disc,
                    'tax_percent' => $taxRate,
                    'cgst_percent' => $item['cgst_percent'] ?? 0,
                    'sgst_percent' => $item['sgst_percent'] ?? 0,
                    'igst_percent' => $item['igst_percent'] ?? 0,
                    'cgst_amount' => $item['cgst_amount'] ?? 0,
                    'sgst_amount' => $item['sgst_amount'] ?? 0,
                    'igst_amount' => $item['igst_amount'] ?? 0,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineSubtotal + $lineTax,
                ]);

                // Sync PR item ordered_qty
                if (!empty($item['requisition_item_id'])) {
                    $prItem = \App\Domains\Purchase\Models\PurchaseRequisitionItem::find($item['requisition_item_id']);
                    if ($prItem) {
                        $prItem->increment('ordered_qty', $qty);
                        if (!$po->purchase_requisition_id) {
                            $po->update(['purchase_requisition_id' => $prItem->purchase_requisition_id]);
                        }
                    }
                } elseif ($po->purchase_requisition_id) {
                    $prItem = \App\Domains\Purchase\Models\PurchaseRequisitionItem::where('purchase_requisition_id', $po->purchase_requisition_id)
                        ->where('product_id', $item['product_id'])
                        ->first();
                    if ($prItem) {
                        $prItem->increment('ordered_qty', $qty);
                    }
                }
            }

            return $po;
        });
    }

    public function confirmOrder(PurchaseOrder $order): bool
    {
        $order->status = 'Approved';
        return $this->orderRepo->update($order, [
            'status' => 'Approved',
        ]);
    }

    public function approveOrder(PurchaseOrder $order): bool
    {
        return $this->confirmOrder($order);
    }

    public function cancelOrder(PurchaseOrder $order): bool
    {
        $order->status = 'Cancelled';
        return $this->orderRepo->update($order, [
            'status' => 'Cancelled',
        ]);
    }
}
