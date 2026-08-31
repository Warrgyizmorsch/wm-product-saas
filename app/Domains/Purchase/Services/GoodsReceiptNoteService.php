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
        $assetLineIds = [];

        $grn = DB::transaction(function () use ($validated, $tenantId, &$assetLineIds) {
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
                $qtyRejected = (float)($item['rejected_qty'] ?? 0);
                $qtyAccepted = max(0.0, $qtyReceived - $qtyRejected);

                $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);

                $orderedQty = (float) $poItem->quantity;
                $prevReceived = (float) ($poItem->received_qty ?? 0.0);

                if (($prevReceived + $qtyReceived) > $orderedQty + 0.0001) {
                    throw new \InvalidArgumentException("Cannot receive {$qtyReceived} units. Total received would exceed ordered quantity {$orderedQty}.");
                }
                $remainingQty = max(0.0, $orderedQty - ($prevReceived + $qtyReceived));
                $unitRate = (float) ($poItem->rate ?? $poItem->unit_price ?? 0.00);
                $totalAmount = round($qtyAccepted * $unitRate, 2);

                $batchNumber = $item['batch_number'] ?? null;
                $mfgDate = $item['manufacturing_date'] ?? null;
                $expiryDate = $item['expiry_date'] ?? null;
                $snRaw = $item['serial_numbers'] ?? '';
                if (is_array($snRaw)) {
                    $serialNumbers = $snRaw;
                } else {
                    $serialNumbers = preg_split('/[\r\n,;]+/', (string)$snRaw);
                }
                $serialNumbers = array_values(array_filter(array_map('trim', $serialNumbers)));

                $grnItem = GoodsReceiptNoteItem::create([
                    'tenant_id'              => $tenantId,
                    'goods_receipt_note_id'  => $grn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id'             => $poItem->product_id,
                    'line_type'              => $poItem->line_type ?? PurchaseOrderItem::LINE_TYPE_STOCK,
                    'chart_of_account_id'    => $poItem->chart_of_account_id,
                    'asset_category_id'      => $poItem->asset_category_id,
                    'production_order_id'           => $poItem->production_order_id ?? $po?->production_order_id,
                    'production_order_operation_id' => $poItem->production_order_operation_id,
                    'production_batch_id'           => $poItem->production_batch_id,
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

                if ($qtyAccepted > 0 && $grnItem->line_type === PurchaseOrderItem::LINE_TYPE_ASSET) {
                    $assetLineIds[] = $grnItem->id;
                }

                if ($qtyAccepted > 0 && $this->shouldAffectPhysicalInventory($poItem)) {
                    if (!empty($item['batches']) && is_array($item['batches'])) {
                        $sumBatchQty = 0;
                        foreach ($item['batches'] as $b) {
                            $sumBatchQty += (float)($b['received_qty'] ?? 0);
                        }
                        if ($sumBatchQty > ($qtyAccepted + 0.0001)) {
                            throw new \Exception("Total batch allocation quantity ({$sumBatchQty}) cannot exceed accepted item quantity ({$qtyAccepted}) for product '{$poItem->product?->name}'.");
                        }

                        foreach ($item['batches'] as $b) {
                            $bQty = (float)($b['received_qty'] ?? 0);
                            if ($bQty <= 0) continue;
                            $bNo = $b['batch_number'] ?? null;
                            $bMfg = $b['manufacturing_date'] ?? null;
                            $bExp = $b['expiry_date'] ?? null;

                            StockService::recordInflow(
                                $tenantId,
                                $poItem->product_id,
                                $warehouseId,
                                $bQty,
                                $unitRate,
                                'Purchase Receipt',
                                $grn->id,
                                $bNo,
                                [],
                                $bMfg,
                                $bExp
                            );
                        }
                    } else {
                        StockService::recordInflow(
                            $tenantId,
                            $poItem->product_id,
                            $warehouseId,
                            $qtyAccepted,
                            $unitRate,
                            'Purchase Receipt',
                            $grn->id,
                            $batchNumber,
                            $serialNumbers,
                            $mfgDate,
                            $expiryDate
                        );
                    }
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
                    $po->update([
                        'status' => 'Completed',
                        'completed_at' => now(),
                    ]);
                } elseif ($partiallyReceived) {
                    $po->update(['status' => 'Partially Received']);
                }
            }

            return $grn;
        });

        DB::afterCommit(function () use ($grn, $assetLineIds) {
            event(new \App\Domains\Purchase\Events\GoodsReceiptNoteApproved($grn));

            foreach (GoodsReceiptNoteItem::whereIn('id', $assetLineIds)->get() as $assetLine) {
                event(new \App\Domains\Purchase\Events\GrnAssetLineReceived($assetLine));
            }
        });

        return $grn;
    }

    /**
     * Centralized decision to determine if a PO item / product receipt affects physical warehouse inventory.
     */
    public function shouldAffectPhysicalInventory(PurchaseOrderItem $poItem): bool
    {
        if ($poItem->line_type && $poItem->line_type !== PurchaseOrderItem::LINE_TYPE_STOCK) {
            return false;
        }

        $product = $poItem->product;
        if ($product && (strtolower((string)$product->item_type) === 'service' || strtolower((string)$product->type) === 'service')) {
            return false;
        }

        if (!empty($poItem->production_order_operation_id)) {
            return false;
        }

        if ($poItem->order && $poItem->order->is_subcontract) {
            $opId = $poItem->production_order_operation_id;
            if ($opId) {
                $op = \App\Domains\Production\Models\ProductionOrderOperation::find($opId);
                if ($op && $op->is_external) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }
}
