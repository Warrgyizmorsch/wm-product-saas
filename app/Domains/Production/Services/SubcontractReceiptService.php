<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionSubcontractReceipt;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The ONE receipt of outsourced work (docs/subcontracting_module_audit_2026-11-26.md SC-02 / SC-03 / TF-06..TF-11).
 * Called by the Subcontracting menu (dispatch slip → Receive) and by the shop floor (MES "Record subcontract receipt").
 * The Purchase GRN screen no longer receives subcontract lines (GoodsReceiptNoteService refuses them).
 *
 * One receipt:
 *  1. checks the operation, the order, the open dispatch slip and the approved subcontract PO (SC-04, my rule: no
 *     approved PO → no receipt), and never takes more than the PO still has open or the slip still has at the vendor;
 *  2. records the receipt and a progress log (good / rejected / scrapped units), the slip's received units and what
 *     the vendor used of the sent material (TF-08: no backflush, no stock movement, no finished-goods stock — TF-09);
 *  3. without QC: books the good units on the operation, the WIP and the next operation, and creates the stock-free
 *     system GRN (accepted / rejected) that moves the PO and lets the vendor bill be entered (SC-03);
 *     with QC (TF-10): the units wait for the operation's normal QC on the shop floor; the QC result books them and
 *     creates the system GRN then (bookAfterQc).
 */
class SubcontractReceiptService
{
    /** PO statuses that may still receive */
    private const OPEN_PO_STATUSES = ['Approved', 'Partially Received'];

    public function __construct(
        private readonly SubcontractMaterialBalanceService $balance,
        private readonly GoodsReceiptNoteService $grnService,
    ) {}

    /**
     * @param array{received_qty:float, rejected_qty?:float, scrapped_qty?:float, remarks?:?string, returns?:array, final?:bool} $data
     */
    public function receive(ProductionOrderOperation $op, array $data, int $userId, string $source, ?DeliveryChallan $challan = null): ProductionSubcontractReceipt
    {
        return DB::transaction(function () use ($op, $data, $userId, $source, $challan) {
            $op = ProductionOrderOperation::with(['order', 'routingOperation'])->lockForUpdate()->findOrFail($op->id);
            $order = $op->order;

            $this->assertOperationCanReceive($op, $order);
            $challan = $this->resolveChallan($op, $challan, $source);
            $isRework = $challan?->type === 'vendor_rework';

            $received = round((float) ($data['received_qty'] ?? 0), 4);
            $rejected = round(max(0.0, (float) ($data['rejected_qty'] ?? 0)), 4);
            $scrapped = round(max(0.0, (float) ($data['scrapped_qty'] ?? 0)), 4);
            if ($received <= 0) {
                throw new InvalidArgumentException('Enter the quantity received from the vendor.');
            }
            if ($rejected + $scrapped > $received + 0.0001) {
                throw new InvalidArgumentException('Rejected + scrapped cannot be more than the quantity received.');
            }
            $good = round($received - $rejected - $scrapped, 4);

            if ($challan && $received > $challan->unitsAtVendor() + 0.0001) {
                throw new InvalidArgumentException('Only ' . $this->fmt($challan->unitsAtVendor()) . " unit(s) are still with the vendor on dispatch slip {$challan->challan_number}.");
            }

            $poItem = $isRework ? null : $this->openPoItem($op);
            if (!$isRework) {
                if (!$poItem) {
                    throw new InvalidArgumentException("No approved subcontract PO for Op #{$op->operation_number} ({$op->name}). Raise the PR / PO with the button and approve the PO before receiving the work.");
                }
                $openOnPo = $this->openOnPo($poItem, $op);
                if ($received > $openOnPo + 0.0001) {
                    throw new InvalidArgumentException('The subcontract PO has ' . $this->fmt($openOnPo) . ' unit(s) left to receive; ' . $this->fmt($received) . ' cannot be received. (Over-receipt is not allowed on subcontract work.)');
                }
            }

            $qcRequired = (bool) ($op->quality_required || ($op->routingOperation?->quality_required ?? false));

            $receipt = ProductionSubcontractReceipt::create([
                'tenant_id' => $op->tenant_id,
                'company_id' => $order?->company_id,
                'branch_id' => $order?->branch_id,
                'receipt_number' => $this->nextNumber((int) $op->tenant_id),
                'production_order_id' => $op->production_order_id,
                'production_order_operation_id' => $op->id,
                'delivery_challan_id' => $challan?->id,
                'purchase_order_item_id' => $poItem?->id,
                'source' => $source,
                'status' => $qcRequired ? ProductionSubcontractReceipt::STATUS_QC_PENDING : ProductionSubcontractReceipt::STATUS_BOOKED,
                'received_qty' => $received,
                'accepted_qty' => $qcRequired ? 0 : $good,
                'rejected_qty' => $rejected,
                'scrapped_qty' => $scrapped,
                'remarks' => $data['remarks'] ?? null,
                'received_by' => $userId,
                'received_at' => now(),
            ]);

            $batchId = ProductionBatch::where('tenant_id', $op->tenant_id)->where('production_order_id', $op->production_order_id)->value('id');

            // N-07 / C-05: real columns — the scrap and rejects survive the quantity reconcile of the order screen
            ProductionOrderProgressLog::create([
                'tenant_id' => $op->tenant_id,
                'production_order_id' => $op->production_order_id,
                'operation_id' => $op->id,
                'production_batch_id' => $batchId,
                'quantity_produced' => $good, // for a QC operation: units waiting for QC (pending QC = logged − inspected)
                'quantity_rejected' => $rejected,
                'quantity_scrapped' => $scrapped,
                'recorded_by' => $userId,
                'recorded_at' => now(),
                'remarks' => mb_substr("Subcontract receipt {$receipt->receipt_number}" . ($challan ? " (slip {$challan->challan_number})" : '') . ': ' . $this->fmt($good) . ' good, ' . $this->fmt($rejected) . ' rejected, ' . $this->fmt($scrapped) . ' scrapped' . ($qcRequired ? ' — waiting for QC' : ''), 0, 250),
            ]);

            if ($challan) {
                $this->updateChallan($challan, $received, $qcRequired ? 0.0 : $good, $rejected, $scrapped, (array) ($data['returns'] ?? []), !empty($data['final']));
            }

            // (rework: MesController::recordDisposition already took the reworked units out of quantity_rejected when the
            // rework slip was made — they simply come back as received units here)

            if ($qcRequired) {
                $op->quantity_rejected = round((float) $op->quantity_rejected + $rejected, 4);
                $op->quantity_scrapped = round((float) $op->quantity_scrapped + $scrapped, 4);
                $op->status = ProductionOrderOperation::STATUS_SUBCONTRACT_QC_PENDING;
                $op->save();
            } else {
                $this->bookGoodUnits($op, $good, $rejected, $scrapped, $batchId, $userId, $receipt);
                if ($poItem) {
                    $this->createSystemGrn($receipt, $poItem, $op, $challan, $received, $rejected + $scrapped);
                }
            }

            app(ProductionEventService::class)->writeEvent((int) $op->tenant_id, [
                'production_order_id' => $op->production_order_id,
                'production_order_operation_id' => $op->id,
                'event_type' => 'Subcontract Received',
                'title' => 'Received from vendor',
                'description' => mb_substr("{$receipt->receipt_number}: " . $this->fmt($received) . ' received (' . $this->fmt($good) . ' good) for Op #' . $op->operation_number . ' via ' . ($source === ProductionSubcontractReceipt::SOURCE_SHOP_FLOOR ? 'shop floor' : 'Subcontracting menu') . '.', 0, 250),
                'severity' => 'success',
                'event_source' => 'SubcontractReceiptService',
                'triggered_by' => $userId,
            ]);

            return $receipt->fresh();
        });
    }

    /**
     * TF-10: the operation's shop-floor QC has booked accepted units on an outsourced operation (QualityInspectionService
     * already added them to the operation, the WIP and the next operation). Finish the waiting receipts in order: each
     * fully inspected receipt is booked and gets its system GRN.
     */
    public function bookAfterQc(ProductionOrderOperation $op, float $accepted, float $rejected, int $userId): void
    {
        $remainingAccepted = max(0.0, $accepted);
        $remainingRejected = max(0.0, $rejected);

        $receipts = ProductionSubcontractReceipt::where('tenant_id', $op->tenant_id)
            ->where('production_order_operation_id', $op->id)
            ->where('status', ProductionSubcontractReceipt::STATUS_QC_PENDING)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($receipts as $receipt) {
            if ($remainingAccepted <= 0 && $remainingRejected <= 0) {
                break;
            }
            $pending = max(0.0, (float) $receipt->received_qty - (float) $receipt->scrapped_qty - (float) $receipt->rejected_qty - (float) $receipt->accepted_qty);
            $takeAccepted = min($pending, $remainingAccepted);
            $takeRejected = min($pending - $takeAccepted, $remainingRejected);
            $remainingAccepted -= $takeAccepted;
            $remainingRejected -= $takeRejected;

            $receipt->accepted_qty = round((float) $receipt->accepted_qty + $takeAccepted, 4);
            $receipt->rejected_qty = round((float) $receipt->rejected_qty + $takeRejected, 4);

            if ($receipt->delivery_challan_id && $takeAccepted > 0) {
                DeliveryChallan::where('id', $receipt->delivery_challan_id)->increment('accepted_qty', $takeAccepted);
            }

            $stillPending = (float) $receipt->received_qty - (float) $receipt->scrapped_qty - (float) $receipt->rejected_qty - (float) $receipt->accepted_qty;
            if ($stillPending <= 0.0001) {
                $receipt->status = (float) $receipt->accepted_qty > 0 ? ProductionSubcontractReceipt::STATUS_BOOKED : ProductionSubcontractReceipt::STATUS_REJECTED_IN_QC;
                $receipt->save();

                $poItem = $receipt->purchase_order_item_id ? PurchaseOrderItem::find($receipt->purchase_order_item_id) : null;
                if ($poItem && !$receipt->goods_receipt_note_id) {
                    $this->createSystemGrn($receipt, $poItem, $op, $receipt->challan, (float) $receipt->received_qty, (float) $receipt->rejected_qty + (float) $receipt->scrapped_qty);
                }
            } else {
                $receipt->save();
            }
        }

        $op->refresh();
        if ($op->status !== ProductionOrderOperation::STATUS_COMPLETED) {
            $stillWaiting = ProductionSubcontractReceipt::where('production_order_operation_id', $op->id)->where('status', ProductionSubcontractReceipt::STATUS_QC_PENDING)->exists();
            $op->status = $stillWaiting
                ? ProductionOrderOperation::STATUS_SUBCONTRACT_QC_PENDING
                : ($this->hasWorkAtVendor($op) ? ProductionOrderOperation::STATUS_VENDOR_DISPATCHED : ProductionOrderOperation::STATUS_RUNNING);
            $op->save();
        }
    }

    // ─── Internals ───────────────────────────────────────────────────────────────

    private function assertOperationCanReceive(ProductionOrderOperation $op, ?ProductionOrder $order): void
    {
        if (!$op->isOutsourced()) {
            throw new InvalidArgumentException("Operation #{$op->operation_number} is not an outsourced operation.");
        }
        if (!$order || !in_array($order->status, [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('Work can be received only on a released order that is not finished.');
        }
        if (in_array($op->status, [ProductionOrderOperation::STATUS_COMPLETED, ProductionOrderOperation::STATUS_CANCELLED, ProductionOrderOperation::STATUS_SKIPPED], true)) {
            throw new InvalidArgumentException("Operation #{$op->operation_number} is already {$op->status}.");
        }
    }

    /**
     * The slip the work comes back on. A given slip must be this operation's and still at the vendor. Without one, the
     * oldest slip at the vendor is used; only a purely vendor-supplied operation may be received with no slip at all.
     */
    private function resolveChallan(ProductionOrderOperation $op, ?DeliveryChallan $challan, string $source): ?DeliveryChallan
    {
        if ($challan) {
            if ((int) $challan->production_order_operation_id !== (int) $op->id) {
                throw new InvalidArgumentException("Dispatch slip {$challan->challan_number} is not for this operation.");
            }
            if (!$challan->isAtVendor()) {
                throw new InvalidArgumentException("Dispatch slip {$challan->challan_number} is {$challan->status}: only a slip that was dispatched can be received.");
            }

            return $challan;
        }

        $open = DeliveryChallan::where('tenant_id', $op->tenant_id)
            ->where('production_order_operation_id', $op->id)
            ->whereIn('status', DeliveryChallan::AT_VENDOR_STATUSES)
            ->orderBy('id')
            ->first();

        if ($open) {
            return $open;
        }

        $needsDispatch = $op->isWipJobWork() || $this->balance->companyMaterialsPerUnit($op)->isNotEmpty();
        if ($needsDispatch) {
            throw new InvalidArgumentException("Nothing is with the vendor for Op #{$op->operation_number}: send the material / WIP on a dispatch slip first.");
        }

        return null; // vendor-supplied operation: the vendor brings everything, there is nothing to dispatch
    }

    private function openPoItem(ProductionOrderOperation $op): ?PurchaseOrderItem
    {
        return PurchaseOrderItem::where('production_order_operation_id', $op->id)
            ->whereHas('order', fn ($q) => $q->where('tenant_id', $op->tenant_id)->whereIn('status', self::OPEN_PO_STATUSES))
            ->orderBy('id')
            ->first();
    }

    /** PO quantity − received on GRNs − receipts still waiting for QC (they get their GRN after QC) */
    private function openOnPo(PurchaseOrderItem $poItem, ProductionOrderOperation $op): float
    {
        $waitingForQc = (float) ProductionSubcontractReceipt::where('purchase_order_item_id', $poItem->id)
            ->where('status', ProductionSubcontractReceipt::STATUS_QC_PENDING)
            ->sum('received_qty');

        return max(0.0, round((float) $poItem->quantity - (float) $poItem->received_qty - $waitingForQc, 4));
    }

    private function updateChallan(DeliveryChallan $challan, float $received, float $accepted, float $rejected, float $scrapped, array $returns, bool $final): void
    {
        $challan->received_qty = round((float) $challan->received_qty + $received, 4);
        $challan->accepted_qty = round((float) $challan->accepted_qty + $accepted, 4);
        $challan->rejected_qty = round((float) $challan->rejected_qty + $rejected, 4);
        $challan->scrapped_qty = round((float) $challan->scrapped_qty + $scrapped, 4);

        $this->balance->recordMaterialUse($challan, $received, $returns);

        $complete = $final || $challan->unitsAtVendor() <= 0.0001;
        $challan->status = $complete ? DeliveryChallan::STATUS_COMPLETED : DeliveryChallan::STATUS_PARTIALLY_RECEIVED;
        $challan->completed_at = $complete ? now() : null;
        $challan->save();
    }

    /** Without QC: the good units count as the operation's output at once */
    private function bookGoodUnits(ProductionOrderOperation $op, float $good, float $rejected, float $scrapped, ?int $batchId, int $userId, ProductionSubcontractReceipt $receipt): void
    {
        $op->quantity_produced = round((float) $op->quantity_produced + $good, 4);
        $op->quantity_rejected = round((float) $op->quantity_rejected + $rejected, 4);
        $op->quantity_scrapped = round((float) $op->quantity_scrapped + $scrapped, 4);

        $target = (float) ($op->target_produced_qty ?: ($op->order?->quantity_ordered ?: 0.0));
        if ($target > 0 && (float) $op->quantity_produced >= $target - 0.0001) {
            $op->status = ProductionOrderOperation::STATUS_COMPLETED;
            $op->actual_end_time = now();
            ProductionScheduleOperation::where('production_order_operation_id', $op->id)
                ->update(['status' => ProductionScheduleOperation::STATUS_COMPLETED, 'actual_finish' => now()]);
        } else {
            $op->status = $this->hasWorkAtVendor($op) ? ProductionOrderOperation::STATUS_VENDOR_DISPATCHED : ProductionOrderOperation::STATUS_RUNNING;
        }
        $op->save();

        if ($good > 0) {
            $wip = ProductionWip::where('tenant_id', $op->tenant_id)->where('production_order_id', $op->production_order_id)->first();
            if (!$wip) {
                $wip = ProductionWip::create([
                    'tenant_id' => $op->tenant_id,
                    'company_id' => $op->order?->company_id,
                    'branch_id' => $op->order?->branch_id,
                    'production_order_id' => $op->production_order_id,
                    'production_batch_id' => $batchId,
                    'product_id' => $op->order?->product_id,
                    'current_routing_operation_id' => $op->routing_operation_id,
                    'quantity' => $op->order?->quantity_ordered ?? 0,
                    'available_quantity' => 0,
                    'completed_quantity' => 0,
                    'rejected_quantity' => 0,
                    'scrap_quantity' => 0,
                    'rework_quantity' => 0,
                    'status' => 'active',
                ]);
            }
            $wip->available_quantity = round((float) $wip->available_quantity + $good, 4);
            $wip->completed_quantity = round((float) $wip->completed_quantity + $good, 4);
            $wip->save();

            ProductionWipTransaction::create([
                'tenant_id' => $op->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $op->production_order_id,
                'production_batch_id' => $batchId,
                'from_operation_id' => $op->routing_operation_id,
                'to_operation_id' => null,
                'from_work_center_id' => null,
                'to_work_center_id' => null,
                'transaction_type' => 'subcontract_received',
                'quantity' => $good,
                'good_quantity' => $good,
                'rejected_quantity' => $rejected,
                'scrap_quantity' => $scrapped,
                'cost_before' => 0.00,
                'cost_added' => 0.00,
                'cost_after' => 0.00,
                'remarks' => mb_substr("Subcontract receipt {$receipt->receipt_number}: " . $this->fmt($good) . ' good unit(s) back from the vendor', 0, 250),
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            app(ProductionWipService::class)->evaluateAndExecuteWipTransfers($op->id, $userId, $batchId);
        }

        app(ProductionOrderService::class)->reconcileOperationReadiness($op->production_order_id);
        app(ProductionOrderService::class)->evaluateAndAutoCompleteOrder($op->production_order_id, $userId);
    }

    /** SC-03: the stock-free GRN that moves the subcontract PO (received / status) and is the base of the vendor bill */
    private function createSystemGrn(ProductionSubcontractReceipt $receipt, PurchaseOrderItem $poItem, ProductionOrderOperation $op, ?DeliveryChallan $challan, float $received, float $rejected): void
    {
        $grn = $this->grnService->storeGrn([
            'purchase_order_id' => $poItem->purchase_order_id,
            'vendor_id' => $op->vendor_id ?? $poItem->order?->vendor_id,
            'received_date' => now()->toDateString(),
            'challan_number' => $challan?->challan_number,
            'notes' => "System GRN for subcontract receipt {$receipt->receipt_number} (Op #{$op->operation_number} {$op->name}).",
            'source_type' => GoodsReceiptNoteService::SOURCE_SUBCONTRACT_RECEIPT,
            'source_id' => $receipt->id,
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $poItem->product_id,
                'received_qty' => $received,
                'rejected_qty' => min($received, $rejected),
                'remarks' => mb_substr("Subcontract receipt {$receipt->receipt_number}", 0, 250),
            ]],
        ], (int) $op->tenant_id);

        $receipt->goods_receipt_note_id = $grn->id;
        $receipt->save();
    }

    private function hasWorkAtVendor(ProductionOrderOperation $op): bool
    {
        return DeliveryChallan::where('tenant_id', $op->tenant_id)
            ->where('production_order_operation_id', $op->id)
            ->whereIn('status', DeliveryChallan::AT_VENDOR_STATUSES)
            ->exists();
    }

    private function nextNumber(int $tenantId): string
    {
        $prefix = 'SCR-' . now()->format('Ym') . '-';
        $last = ProductionSubcontractReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('receipt_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('receipt_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        do {
            $number = $prefix . str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
        } while (ProductionSubcontractReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('receipt_number', $number)->exists());

        return $number;
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
