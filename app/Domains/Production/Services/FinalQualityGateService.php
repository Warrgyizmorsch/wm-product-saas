<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionWip;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Finished-product QC gate (docs/production_quality_rules.md QR-04 / QR-18):
 * when the order's product has Approved product plans, finished goods can be received into a warehouse only up to the
 * quantity accepted by the final QC done in the WIP section. Rejected units can never be received.
 *
 *   allowed to receive = final QC accepted − already received
 *   waiting for final QC = WIP ready for conversion − (final QC inspected − already received)
 */
class FinalQualityGateService
{
    public function __construct(private readonly OrderOperationQualityService $qualityService)
    {
    }

    /** A4: every approved product plan of the order's product (all mandatory). */
    public function plans(ProductionOrder $order): Collection
    {
        return $order->product_id
            ? $this->qualityService->approvedProductPlans((int) $order->tenant_id, (int) $order->product_id)
            : collect();
    }

    public function requiresFinalQc(ProductionOrder $order): bool
    {
        return $this->plans($order)->isNotEmpty();
    }

    public function summary(ProductionOrder $order): array
    {
        $required = $this->requiresFinalQc($order);

        $final = ProductionQualityInspection::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->whereNull('production_order_operation_id')
            ->where('stage', 'final')
            ->where('status', 'approved')
            ->selectRaw('COALESCE(SUM(passed_qty), 0) as accepted, COALESCE(SUM(failed_qty), 0) as rejected, COALESCE(SUM(COALESCE(inspected_quantity, passed_qty + failed_qty)), 0) as inspected')
            ->first();

        $accepted = (float) ($final->accepted ?? 0);
        $rejected = (float) ($final->rejected ?? 0);
        $inspected = (float) ($final->inspected ?? 0);
        $received = (float) ProductionOrderReceipt::where('production_order_id', $order->id)->sum('quantity_received');
        $wipReady = $this->wipReadyQty($order);

        return [
            'required' => $required,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'inspected' => $inspected,
            'received' => $received,
            'wip_ready' => $wipReady,
            'allowed' => $required ? max(0.0, $accepted - $received) : null,
            'pending' => $required ? max(0.0, $wipReady - max(0.0, $inspected - $received)) : 0.0,
        ];
    }

    /** Null when the product has no final QC; otherwise the quantity that may still be received. */
    public function allowedToReceive(ProductionOrder $order): ?float
    {
        return $this->summary($order)['allowed'];
    }

    public function assertCanReceive(ProductionOrder $order, float $quantity): void
    {
        $allowed = $this->allowedToReceive($order);
        if ($allowed === null) {
            return;
        }

        if ($quantity > $allowed + 0.0001) {
            $product = $order->product;
            $label = $product ? trim(($product->sku ? $product->sku . ' - ' : '') . $product->name) : 'this product';
            $fmt = static fn (float $v) => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
            $pending = $fmt(max(0.0, $quantity - $allowed));

            throw new InvalidArgumentException(
                "Final QC pending for {$pending} unit(s) of {$label}. Only {$fmt($allowed)} unit(s) passed final QC and can be received — perform the final quality check in WIP first."
            );
        }
    }

    /** Completed WIP waiting to be converted to finished goods (same cards the bulk "Receive completed FG" uses). */
    public function wipReadyQty(ProductionOrder $order): float
    {
        return (float) ProductionWip::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->whereNotIn('status', ['quality_hold', 'rework'])
            ->get()
            ->sum(fn (ProductionWip $wip) => (float) ($wip->completed_quantity > 0
                ? $wip->completed_quantity
                : ($wip->status === 'completed' ? $wip->available_quantity : 0.0)));
    }
}
