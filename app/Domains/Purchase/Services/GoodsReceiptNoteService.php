<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Repositories\GoodsReceiptNoteRepository;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Support\Facades\DB;

class GoodsReceiptNoteService
{
    public function __construct(
        protected GoodsReceiptNoteRepository $grnRepo
    ) {}

    public function storeGrn(array $validated, int $tenantId): GoodsReceiptNote
    {
        return DB::transaction(function () use ($validated, $tenantId) {
            $grnNumber = $this->grnRepo->getNextGrnNumber($tenantId);

            $po = PurchaseOrder::find($validated['purchase_order_id']);
            $vendorId = $validated['vendor_id'] ?? $po?->vendor_id;
            $warehouseId = $validated['warehouse_id'] ?? $po?->warehouse_id ?? Warehouse::where('tenant_id', $tenantId)->first()?->id;

            $grn = $this->grnRepo->create([
                'tenant_id'         => $tenantId,
                'grn_number'         => $grnNumber,
                'purchase_order_id' => $validated['purchase_order_id'],
                'vendor_id'         => $vendorId,
                'warehouse_id'      => $warehouseId,
                'received_date'     => $validated['received_date'] ?? $validated['receipt_date'] ?? now()->toDateString(),
                'challan_number'    => $validated['challan_number'] ?? $validated['chalan_number'] ?? null,
                'challan_date'      => $validated['challan_date'] ?? $validated['chalan_date'] ?? null,
                'vehicle_number'    => $validated['vehicle_number'] ?? null,
                'transporter_name'  => $validated['transporter_name'] ?? null,
                'lr_number'         => $validated['lr_number'] ?? null,
                'status'            => 'Approved',
                'notes'             => $validated['notes'] ?? null,
                'created_by'        => auth()->id() ?: 1,
                'approved_by'       => auth()->id() ?: 1,
                'approved_at'       => now(),
            ]);

            foreach ($validated['items'] as $item) {
                $qtyReceived = (float)$item['received_qty'];
                $qtyAccepted = (float)($item['accepted_qty'] ?? $qtyReceived);
                $qtyRejected = (float)($item['rejected_qty'] ?? 0);

                $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);

                $orderedQty = (float) $poItem->quantity;
                $prevReceived = (float) ($poItem->received_qty ?? 0.0);
                $remainingQty = max(0.0, $orderedQty - ($prevReceived + $qtyReceived));
                $unitRate = (float) ($poItem->rate ?? $poItem->unit_price ?? 0.00);
                $totalAmount = round($qtyAccepted * $unitRate, 2);

                $batchNumber = $item['batch_number'] ?? null;
                $snRaw = $item['serial_numbers'] ?? '';
                $serialNumbers = is_array($snRaw) ? $snRaw : array_filter(array_map('trim', explode(',', (string)$snRaw)));

                $grnItem = GoodsReceiptNoteItem::create([
                    'tenant_id'              => $tenantId,
                    'goods_receipt_note_id'  => $grn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id'             => $poItem->product_id,
                    'ordered_qty'            => $orderedQty,
                    'previous_received_qty'  => $prevReceived,
                    'received_qty'           => $qtyReceived,
                    'accepted_qty'           => $qtyAccepted,
                    'rejected_qty'           => $qtyRejected,
                    'remaining_qty'          => $remainingQty,
                    'unit_rate'              => $unitRate,
                    'total_amount'           => $totalAmount,
                    'remarks'                => $item['remarks'] ?? null,
                ]);

                if ($qtyAccepted > 0) {
                    StockService::recordInflow(
                        $tenantId,
                        $poItem->product_id,
                        $warehouseId,
                        $qtyAccepted,
                        $unitRate,
                        'Purchase Receipt',
                        $grn->id,
                        $batchNumber,
                        $serialNumbers
                    );
                }

                $poItem->increment('received_qty', $qtyReceived);
            }

            if ($po) {
                $allReceived = true;
                $partiallyReceived = false;

                foreach ($po->fresh()->items as $pi) {
                    if ($pi->received_qty >= $pi->quantity) {
                        $partiallyReceived = true;
                    } else {
                        $allReceived = false;
                        if ($pi->received_qty > 0) {
                            $partiallyReceived = true;
                        }
                    }
                }

                if ($allReceived) {
                    $po->update(['status' => 'Completed']);
                } elseif ($partiallyReceived) {
                    $po->update(['status' => 'Partially Received']);
                }
            }

            return $grn;
        });
    }
}
