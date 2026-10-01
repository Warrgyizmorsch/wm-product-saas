<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Events\BillPosted;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 3-way match: compares each vendor-bill line with the purchase-order line
 * (what we agreed to pay) and the GRN line (what we actually accepted).
 *
 *  - Quantity: everything billed so far against a GRN line, this bill
 *    included, must not exceed the accepted quantity (+ tolerance).
 *    Without a GRN (2-way), against the PO quantity instead.
 *  - Price:    the billed rate must not exceed the PO rate (+ tolerance).
 *    Billing below the PO rate is never an exception.
 *
 * Lines with neither a GRN nor a PO line (service / direct bills) are
 * "not applicable" and never hold a bill.
 */
class ThreeWayMatchService
{
    private const QTY_EPSILON = 0.0005;

    public function __construct(
        private readonly ThreeWayMatchSettings $settings,
    ) {
    }

    /**
     * Check a bill whose lines are saved, store the result on it, and in
     * 'hold' mode put it On Hold when anything fails. Call before posting.
     *
     * @return bool true when the bill is now On Hold (do not post it)
     */
    public function apply(VendorBill $bill): bool
    {
        $settings = $this->settings->for((int) $bill->tenant_id);

        if ($settings['mode'] === ThreeWayMatchSettings::MODE_OFF) {
            return false;
        }

        $result = $this->evaluate($bill, $settings);
        $hold = $settings['mode'] === ThreeWayMatchSettings::MODE_HOLD
            && $result['status'] === VendorBill::MATCH_EXCEPTION;

        $bill->forceFill([
            'match_status' => $result['status'],
            'match_details' => $result,
            'match_checked_at' => now(),
        ] + ($hold ? ['status' => VendorBill::STATUS_ON_HOLD] : []))->save();

        return $hold;
    }

    /**
     * @param array{mode: string, qty_tolerance_percent: float, price_tolerance_percent: float}|null $settings
     * @return array{status: string, qty_tolerance_percent: float, price_tolerance_percent: float, lines: array<int, array<string, mixed>>}
     */
    public function evaluate(VendorBill $bill, ?array $settings = null): array
    {
        $settings ??= $this->settings->for((int) $bill->tenant_id);
        $bill->loadMissing(['items.product', 'goodsReceiptNote.items', 'purchaseOrder.items']);

        $lines = [];
        foreach ($bill->items as $item) {
            $lines[] = $this->evaluateLine($bill, $item, $settings);
        }

        $applicable = array_filter($lines, fn ($line) => $line['basis'] !== 'none');
        $status = match (true) {
            $applicable === [] => VendorBill::MATCH_NOT_APPLICABLE,
            (bool) array_filter($applicable, fn ($line) => !$line['qty_ok'] || !$line['price_ok']) => VendorBill::MATCH_EXCEPTION,
            default => VendorBill::MATCH_MATCHED,
        };

        return [
            'status' => $status,
            'qty_tolerance_percent' => $settings['qty_tolerance_percent'],
            'price_tolerance_percent' => $settings['price_tolerance_percent'],
            'lines' => $lines,
        ];
    }

    /**
     * Release a held bill: it becomes a normal unpaid bill and is posted to
     * the ledger now. The person who entered the bill cannot release it.
     */
    public function release(VendorBill $bill, int $userId, string $reason): VendorBill
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason for releasing this bill.');
        }

        $released = DB::transaction(function () use ($bill, $userId, $reason) {
            $locked = VendorBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (!$locked->isOnHold()) {
                throw new InvalidArgumentException("Bill {$locked->bill_number} is not on hold.");
            }

            if ((int) $locked->created_by === $userId) {
                throw new InvalidArgumentException('You entered this bill, so someone else has to release it.');
            }

            $locked->forceFill([
                'status' => 'Unpaid',
                'hold_released_by' => $userId,
                'hold_released_at' => now(),
                'hold_release_reason' => mb_substr($reason, 0, 500),
            ])->save();

            return $locked;
        });

        // Now it may post: the accounting listener skips only On Hold bills.
        event(new BillPosted($released));

        return $released->fresh();
    }

    private function evaluateLine(VendorBill $bill, VendorBillItem $item, array $settings): array
    {
        $grnItem = $this->grnItemFor($bill, $item);
        $poItem = $grnItem?->purchaseOrderItem ?? $this->poItemFor($bill, $item);
        $basis = $grnItem ? '3-way' : ($poItem ? '2-way' : 'none');

        $billQty = (float) $item->quantity;
        $billRate = (float) $item->unit_rate;

        $line = [
            'bill_item_id' => $item->id,
            'product' => $item->product?->name ?? ('Item #' . $item->product_id),
            'basis' => $basis,
            'bill_qty' => round($billQty, 3),
            'bill_rate' => round($billRate, 2),
            'po_qty' => $poItem ? round((float) $poItem->quantity, 3) : null,
            'po_rate' => $poItem ? round((float) $poItem->rate, 2) : null,
            'received_qty' => $grnItem ? round($this->acceptedQty($grnItem), 3) : null,
            'billed_before_qty' => 0.0,
            'allowed_qty' => null,
            'price_variance_percent' => null,
            'qty_ok' => true,
            'price_ok' => true,
            'messages' => [],
        ];

        if ($basis === 'none') {
            return $line;
        }

        // Quantity
        $limitQty = $grnItem ? $this->acceptedQty($grnItem) : (float) $poItem->quantity;
        $billedBefore = $grnItem
            ? $this->billedBeforeForGrnItem($bill, $grnItem)
            : $this->billedBeforeForPoItem($bill, $poItem);
        $allowed = $limitQty * (1 + $settings['qty_tolerance_percent'] / 100);

        $line['billed_before_qty'] = round($billedBefore, 3);
        $line['allowed_qty'] = round($allowed, 3);

        if ($billedBefore + $billQty > $allowed + self::QTY_EPSILON) {
            $line['qty_ok'] = false;
            $line['messages'][] = sprintf(
                'Billed %s%s but only %s %s.',
                $this->qty($billedBefore + $billQty),
                $billedBefore > 0 ? ' (incl. ' . $this->qty($billedBefore) . ' on earlier bills)' : '',
                $this->qty($limitQty),
                $grnItem ? 'were accepted' : 'were ordered'
            );
        }

        // Price
        if ($poItem && (float) $poItem->rate > 0) {
            $poRate = (float) $poItem->rate;
            $variance = round(($billRate - $poRate) / $poRate * 100, 2);
            $line['price_variance_percent'] = $variance;

            if ($variance > $settings['price_tolerance_percent'] + 0.0001) {
                $line['price_ok'] = false;
                $line['messages'][] = sprintf('Rate %s is %s%% above the PO rate %s.', number_format($billRate, 2), $variance, number_format($poRate, 2));
            }
        }

        return $line;
    }

    private function grnItemFor(VendorBill $bill, VendorBillItem $item): ?GoodsReceiptNoteItem
    {
        if ($item->goods_receipt_note_item_id) {
            return GoodsReceiptNoteItem::query()->with('purchaseOrderItem')->find($item->goods_receipt_note_item_id);
        }

        // Bills made through the API carry the GRN on the header only.
        return $bill->goodsReceiptNote?->items->firstWhere('product_id', $item->product_id)?->loadMissing('purchaseOrderItem');
    }

    private function poItemFor(VendorBill $bill, VendorBillItem $item): ?PurchaseOrderItem
    {
        return $bill->purchaseOrder?->items->firstWhere('product_id', $item->product_id);
    }

    private function acceptedQty(GoodsReceiptNoteItem $grnItem): float
    {
        $accepted = (float) $grnItem->accepted_qty;

        return $accepted > 0 ? $accepted : max(0.0, (float) $grnItem->received_qty - (float) $grnItem->rejected_qty);
    }

    private function billedBeforeForGrnItem(VendorBill $bill, GoodsReceiptNoteItem $grnItem): float
    {
        return (float) VendorBillItem::query()
            ->where('vendor_bill_id', '!=', $bill->id)
            ->whereHas('bill', fn ($q) => $q->where('status', '!=', 'Cancelled'))
            ->where(fn ($q) => $q
                ->where('goods_receipt_note_item_id', $grnItem->id)
                ->orWhere(fn ($q) => $q
                    ->whereNull('goods_receipt_note_item_id')
                    ->where('product_id', $grnItem->product_id)
                    ->whereHas('bill', fn ($b) => $b->where('goods_receipt_note_id', $grnItem->goods_receipt_note_id))))
            ->sum('quantity');
    }

    private function billedBeforeForPoItem(VendorBill $bill, PurchaseOrderItem $poItem): float
    {
        return (float) VendorBillItem::query()
            ->where('vendor_bill_id', '!=', $bill->id)
            ->where('product_id', $poItem->product_id)
            ->whereHas('bill', fn ($q) => $q
                ->where('status', '!=', 'Cancelled')
                ->where('purchase_order_id', $poItem->purchase_order_id))
            ->sum('quantity');
    }

    private function qty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }
}
