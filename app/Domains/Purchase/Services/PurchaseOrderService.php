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

            $subtotal = 0;
            $taxAmount = 0;
            $discountAmount = $validated['discount_amount'] ?? 0;

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? $item['rate'] ?? 0);
                $disc = (float)($item['discount'] ?? $item['discount_amount'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? $item['tax_percent'] ?? 0);

                $lineSubtotal = ($qty * $price) - $disc;
                $lineTax = $lineSubtotal * ($taxRate / 100);
                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
            }

            $totalAmount = max(0, $subtotal + $taxAmount - $discountAmount);

            $po = $this->orderRepo->create([
                'tenant_id' => $tenantId,
                'purchase_order_number' => $poNumber,
                'date' => $validated['date'] ?? $validated['po_date'] ?? now()->toDateString(),
                'delivery_date' => $validated['delivery_date'] ?? $validated['expected_delivery_date'] ?? null,
                'vendor_id' => $validated['vendor_id'],
                'status' => 'Draft',
                'discount_type' => $validated['discount_type'] ?? 'without_discount',
                'tax_type' => $validated['tax_type'] ?? 'order_wise_tax',
                'gst_type' => $validated['gst_type'] ?? 'cgst_sgst',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'grand_total' => $totalAmount,
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
                    'quantity' => $qty,
                    'rate' => $price,
                    'amount' => $lineSubtotal,
                    'discount_amount' => $disc,
                    'tax_percent' => $taxRate,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineSubtotal + $lineTax,
                ]);
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
