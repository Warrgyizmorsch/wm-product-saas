<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Purchase\Services\PurchaseRequisitionService;
use Illuminate\Support\Facades\DB;

class SubcontractProcurementOrchestrator
{
    public function __construct(
        protected PurchaseRequisitionService $prService
    ) {}

    /**
     * Generate a Purchase Requisition for an outsourced production operation.
     */
    public function generateSubcontractRequisition(
        ProductionOrderOperation $op,
        int $tenantId,
        ?int $userId = null
    ): PurchaseRequisition {
        return DB::transaction(function () use ($op, $tenantId, $userId) {
            if (!$op->is_external) {
                throw new \InvalidArgumentException("Operation {$op->name} is not an outsourced operation.");
            }

            $order = $op->order;
            if (!$order) {
                throw new \InvalidArgumentException("Operation {$op->name} has no associated production order.");
            }

            // Determine product to procure (subcontract service product or FG product)
            $productId = $op->subcontract_service_product_id ?? $order->product_id;
            $qty = (float) $order->quantity_ordered;
            $unitCost = (float) ($op->subcontract_cost_per_unit ?? 0.0);

            // Check if PR already exists for this order/operation to prevent duplicate generation
            $existingPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->whereIn('source_type', ['mo', 'ProductionOrder'])
                ->where('source_id', $order->id)
                ->first();

            if ($existingPr) {
                return $existingPr;
            }

            // Create Requisition via standard PurchaseRequisitionService
            $payload = [
                'requisition_date' => now()->toDateString(),
                'source_type' => 'mo',
                'production_order_id' => $order->id,
                'notes' => "Subcontract PR for Order #{$order->order_number}, Op #{$op->sequence} ({$op->name})",
                'items' => [
                    [
                        'product_id' => $productId,
                        'quantity' => $qty,
                        'estimated_cost' => $unitCost,
                    ]
                ],
            ];

            $pr = $this->prService->storeRequisition($payload, $tenantId);

            // Attach production operation reference to PR item
            PurchaseRequisitionItem::where('purchase_requisition_id', $pr->id)
                ->update([
                    'sales_order_item_id' => $order->sales_order_id ? null : null,
                ]);

            return $pr;
        });
    }
}
