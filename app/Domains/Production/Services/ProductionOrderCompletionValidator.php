<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use InvalidArgumentException;

class ProductionOrderCompletionValidator
{
    public function __construct(
        protected SubcontractMaterialBalanceService $materialBalanceService
    ) {}

    /**
     * Validate whether a ProductionOrder is eligible for completion / full finished goods receipt.
     *
     * @throws InvalidArgumentException When order has outstanding subcontracting, open QC, open rework, or unreconciled material.
     */
    public function validateCompletion(ProductionOrder $order): void
    {
        $tenantId = $order->tenant_id;

        // 1. Check for outstanding subcontract operations (subcontract_qc_pending or incomplete external operations)
        $hasPendingSubcontractOp = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->where('is_external', true)
            ->whereIn('status', ['ready', 'subcontract_qc_pending', 'in_process'])
            ->exists();

        if ($hasPendingSubcontractOp) {
            throw new InvalidArgumentException(
                "Production Order #{$order->order_number} cannot be completed: There are outstanding subcontract operations awaiting completion or Quality Clearance."
            );
        }

        // 2. Check for WIP quality hold or active rework loops
        $hasWipHold = ProductionWip::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->whereIn('status', ['quality_hold', 'rework'])
            ->exists();

        $hasPendingRework = ProductionOrderRework::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasWipHold || $hasPendingRework) {
            throw new InvalidArgumentException(
                "Production Order #{$order->order_number} cannot be completed: This production order has Work-in-Progress (WIP) on Quality Hold or pending Rework. Quality clearance is required before receiving finished goods into inventory."
            );
        }

        // 4. Check for open Non-Conformance Reports (NCRs) requiring disposition
        if ($order->status === 'completed') {
            $hasOpenNcr = ProductionNcr::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->whereIn('status', ['open', 'under_review'])
                ->exists();

            if ($hasOpenNcr) {
                throw new InvalidArgumentException(
                    "Production Order #{$order->order_number} cannot be completed: Open Non-Conformance Reports (NCRs) require disposition."
                );
            }
        }

        // 5. Check for unreconciled company material at vendor
        if ($order->production_model === 'subcontract_company_material' || $order->production_model === 'hybrid') {
            $externalOps = ProductionOrderOperation::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->where('is_external', true)
                ->where('material_supply_type', 'company_supplied')
                ->get();

            foreach ($externalOps as $extOp) {
                $balance = $this->materialBalanceService->getMaterialBalance($tenantId, $order->id, $extOp->id);
                if ($balance['remaining'] > 0.0001) {
                    throw new InvalidArgumentException(
                        "Production Order #{$order->order_number} cannot be completed: Company material remains at subcontractor warehouse ({$balance['remaining']} units unconsumed/unreturned)."
                    );
                }
            }
        }

        // 6. Multi-level intermediate SFG BOM ratio & claims completion safeguards (Rule 13)
        $intermediateOps = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->where('is_intermediate', true)
            ->with(['sourceProduct'])
            ->get();

        foreach ($intermediateOps as $intOp) {
            // Check per-component BOM ratio requirements
            $childProductId = $intOp->source_product_id;
            if ($childProductId) {
                $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $tenantId)
                    ->where('bom_id', $order->bom_id)
                    ->where('material_id', $childProductId)
                    ->first();
                $bomRatio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;

                $requiredSfg = (float) $order->quantity_ordered * $bomRatio;
                $reservedStock = (float) \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('product_id', $childProductId)
                    ->sum('reserved_qty');

                $produced = (float) $intOp->quantity_produced;
                $qcHold = (float) ($intOp->active_qc_hold ?? 0.0);
                $rework = (float) ($intOp->active_rework ?? 0.0);
                $usableProduced = max(0.0, $produced - ($qcHold + $rework));
                $totalAvailable = $reservedStock + $usableProduced;

                if ($totalAvailable < ($requiredSfg - 0.0001) && $intOp->status !== ProductionOrderOperation::STATUS_COMPLETED) {
                    $prodName = $intOp->sourceProduct ? $intOp->sourceProduct->name : "Product #{$childProductId}";
                    throw new InvalidArgumentException(
                        "Production Order #{$order->order_number} cannot be completed: Intermediate SFG component {$prodName} has insufficient usable output (" . number_format($totalAvailable, 2) . ") to support finished goods requirement of " . number_format($requiredSfg, 2) . "."
                    );
                }
            }

            // Check for unresolved claims against total processed quantity (produced + rejected + scrapped) (F-01)
            $totalProcessed = (float) $intOp->quantity_produced + (float) ($intOp->quantity_rejected ?? 0.0) + (float) ($intOp->quantity_scrapped ?? 0.0);
            if ((float) $intOp->quantity_claimed > ($totalProcessed + 0.0001)) {
                throw new InvalidArgumentException(
                    "Production Order #{$order->order_number} cannot be completed: Operation {$intOp->operation_number} has unresolved shop floor claims (Claimed: " . number_format($intOp->quantity_claimed, 2) . ", Total Processed: " . number_format($totalProcessed, 2) . ")."
                );
            }
        }
    }
}
