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
    }
}
