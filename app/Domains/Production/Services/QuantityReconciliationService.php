<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;

/**
 * QuantityReconciliationService
 *
 * Computes variance and reconciliation metrics for a production order.
 *
 * ── Quantity Definitions ────────────────────────────────────────────────────────
 *
 * Production Order quantities are defined as follows to avoid double-counting:
 *
 *  quantity_ordered    The target quantity specified when the order was created.
 *
 *  quantity_produced   Total finished goods formally received into inventory via
 *                      receiveFinishedGoods(). This is the only method that increments
 *                      this field. Reworked units are NOT counted here until they
 *                      re-emerge as finished goods and pass through receiveFinishedGoods().
 *
 *  quantity_scrapped   Total finished/semi-finished goods permanently destroyed, as recorded
 *                      by logScrap(). Updated on the order only for the order's own product.
 *                      Raw material scrap is tracked on the operation/progress log only.
 *
 *  quantity_rejected   Tracked at operation level (ProductionOrderOperation.quantity_rejected)
 *                      and in progress logs. NOT a field on ProductionOrder — rejected units
 *                      may go to rework or scrap and must not be double-counted.
 *
 *  rework_pending_qty  Sum of all pending rework records. Units in rework are NOT counted
 *                      in quantity_produced or quantity_scrapped until resolved.
 *
 * ── Reconciliation Formulas ──────────────────────────────────────────────────────
 *
 *  Total output         = quantity_produced + quantity_scrapped + rework_pending_qty
 *  Production variance  = quantity_ordered − quantity_produced
 *  Material used        = SUM(production_order_issues.quantity_issued) for standard+additional issues
 *  Material returned    = SUM(ABS(production_order_issues.quantity_issued)) for return issues
 *  Net material issued  = material_used − material_returned
 *  Receipt total        = SUM(production_order_receipts.quantity_received)
 *
 *  FG receipt matches produced:  receipt_total should equal quantity_produced.
 *                                Variance here indicates receipts were logged separately
 *                                from the order quantity_produced field.
 *
 * ── Double-Counting Risks ────────────────────────────────────────────────────────
 *
 *  Do NOT sum: quantity_produced + operation.quantity_produced
 *    Operation-level quantity_produced (ProductionOrderOperation) tracks per-step output
 *    for OEE; it is NOT the same as order-level finished goods.
 *
 *  Do NOT sum: quantity_scrapped + pending reworks
 *    Rework units are still "in process"; they have not been permanently lost.
 *
 *  Do NOT sum: quantity_produced + rework_completed_qty
 *    Reworked units that become good output are captured by receiveFinishedGoods()
 *    which increments quantity_produced. No separate rework quantity is added.
 */
class QuantityReconciliationService
{
    /**
     * Compute a full reconciliation report for the given production order.
     *
     * @param int $orderId
     * @return array{
     *   order_id: int,
     *   order_number: string,
     *   quantity_ordered: float,
     *   quantity_produced: float,
     *   quantity_scrapped: float,
     *   rework_pending_qty: float,
     *   rework_completed_qty: float,
     *   total_accounted: float,
     *   production_variance: float,
     *   material_issued_qty: float,
     *   material_returned_qty: float,
     *   net_material_issued_qty: float,
     *   receipt_total_qty: float,
     *   receipt_vs_produced_variance: float,
     *   warnings: string[]
     * }
     */
    public function reconcileOrder(int $orderId): array
    {
        $order = ProductionOrder::with(['issues', 'receipts', 'reworks', 'scraps'])->findOrFail($orderId);

        // ── Material quantities ───────────────────────────────────────────────
        $materialIssued   = 0.0;
        $materialReturned = 0.0;

        foreach ($order->issues as $issue) {
            if ($issue->quantity_issued > 0) {
                $materialIssued += (float) $issue->quantity_issued;
            } else {
                // Negative quantity_issued = return (stored as negative)
                $materialReturned += abs((float) $issue->quantity_issued);
            }
        }

        // ── Rework quantities ─────────────────────────────────────────────────
        $reworkPendingQty   = 0.0;
        $reworkCompletedQty = 0.0;

        foreach ($order->reworks as $rework) {
            if ($rework->status === 'pending') {
                $reworkPendingQty += (float) $rework->quantity;
            } elseif ($rework->status === 'completed') {
                $reworkCompletedQty += (float) $rework->quantity;
            }
        }

        // ── Production output quantities ──────────────────────────────────────
        $quantityProduced = (float) $order->quantity_produced;
        $quantityScrapped = (float) $order->quantity_scrapped;
        $quantityOrdered  = (float) $order->quantity_ordered;

        // Total accounted = finished goods + scrapped + still-in-rework
        // Completed rework is NOT added separately because those units re-emerge
        // through receiveFinishedGoods() and are already in quantity_produced.
        $totalAccounted       = $quantityProduced + $quantityScrapped + $reworkPendingQty;
        $productionVariance   = $quantityOrdered - $quantityProduced;
        $netMaterialIssued    = $materialIssued - $materialReturned;

        // ── Receipt total ─────────────────────────────────────────────────────
        $receiptTotal = (float) $order->receipts->sum('quantity_received');

        // Variance between receipt records and order.quantity_produced
        // These should match; a non-zero variance may indicate data entry inconsistency.
        $receiptVsProducedVariance = $receiptTotal - $quantityProduced;

        // ── Warnings ─────────────────────────────────────────────────────────
        $warnings = [];

        if ($reworkPendingQty > 0) {
            $warnings[] = "{$reworkPendingQty} units are still in pending rework and have not been resolved.";
        }

        if (abs($receiptVsProducedVariance) > 0.0001) {
            $warnings[] = "Receipt total ({$receiptTotal}) does not match order quantity_produced ({$quantityProduced}). Variance: {$receiptVsProducedVariance}.";
        }

        if ($totalAccounted > $quantityOrdered + 0.0001) {
            $warnings[] = "Total accounted output ({$totalAccounted}) exceeds quantity ordered ({$quantityOrdered}). Check for over-production or double-logging.";
        }

        return [
            'order_id'                    => $order->id,
            'order_number'                => $order->order_number,
            'quantity_ordered'            => $quantityOrdered,
            'quantity_produced'           => $quantityProduced,
            'quantity_scrapped'           => $quantityScrapped,
            'rework_pending_qty'          => $reworkPendingQty,
            'rework_completed_qty'        => $reworkCompletedQty,
            'total_accounted'             => $totalAccounted,
            'production_variance'         => $productionVariance,
            'material_issued_qty'         => $materialIssued,
            'material_returned_qty'       => $materialReturned,
            'net_material_issued_qty'     => $netMaterialIssued,
            'receipt_total_qty'           => $receiptTotal,
            'receipt_vs_produced_variance'=> $receiptVsProducedVariance,
            'warnings'                    => $warnings,
        ];
    }
}
