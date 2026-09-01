<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use App\Domains\Purchase\Services\PurchaseOrderService;
use App\Domains\Purchase\Services\PurchaseRequisitionService;
use Illuminate\Support\Facades\DB;

class SubcontractProcurementOrchestrator
{
    public function __construct(
        protected PurchaseRequisitionService $prService,
        protected PurchaseOrderService $poService,
        protected PurchaseOrderRepository $poRepo,
        protected SubcontractProcurementPolicyResolver $policyResolver
    ) {}

    /**
     * Master entry point to orchestrate subcontract procurement according to tenant policy.
     */
    public function orchestrateSubcontractProcurement(
        ProductionOrderOperation $op,
        int $tenantId,
        ?int $userId = null
    ): mixed {
        if (!$op->is_external) {
            return null;
        }

        $order = $op->order ?? \App\Domains\Production\Models\ProductionOrder::find($op->production_order_id);
        if (!$order) {
            return null;
        }

        // Idempotency check: verify if active PO already exists for this operation
        $existingPoItem = PurchaseOrderItem::where('production_order_operation_id', $op->id)
            ->whereHas('order', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->where('status', '!=', 'Cancelled');
            })
            ->with('order')
            ->first();

        if ($existingPoItem && $existingPoItem->order) {
            return $existingPoItem->order;
        }

        // Idempotency check: verify if active PR already exists for this operation
        $existingPr = PurchaseRequisition::where('tenant_id', $tenantId)
            ->whereIn('source_type', ['mo', 'ProductionOrder'])
            ->where('source_id', $order->id)
            ->where('notes', 'like', "%Op #{$op->sequence}%")
            ->where('status', '!=', 'Cancelled')
            ->first();

        // Resolve Policy
        $policy = $this->policyResolver->resolvePolicy($op, $tenantId);

        if (!$policy['is_valid']) {
            // Log setup issue for audit trail
            ProductionOrderProgressLog::create([
                'tenant_id' => $tenantId,
                'production_order_id' => $order->id,
                'operation_id' => $op->id,
                'quantity_completed' => 0,
                'recorded_at' => now(),
                'status' => $op->status,
                'log_type' => 'subcontract_setup_required',
                'logged_by' => $userId ?: 1,
                'remarks' => "Subcontract Setup Required: " . implode(' ', $policy['validation_errors']),
            ]);

            return null;
        }

        if ($existingPr && $policy['effective_workflow'] === 'manual_pr_po') {
            return $existingPr;
        }

        return DB::transaction(function () use ($op, $order, $tenantId, $userId, $policy) {
            if ($policy['effective_workflow'] === 'manual_pr_po') {
                return $this->generateSubcontractRequisition($op, $tenantId, $userId);
            }

            // Mode: auto_draft_po or auto_approved_po
            $poNumber = $this->poRepo->getNextPoNumber($tenantId);
            $status = ($policy['effective_workflow'] === 'auto_approved_po' && $policy['can_auto_approve']) ? 'Approved' : 'Draft';
            
            $sourceNote = "Production Subcontract · MO #{$order->order_number} · Op #{$op->sequence} ({$op->name})";
            if (!empty($policy['fallback_reason'])) {
                $sourceNote .= " [Auto-Approval Skipped: {$policy['fallback_reason']}]";
            }

            $po = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'purchase_order_number' => $poNumber,
                'vendor_id' => $policy['vendor_id'],
                'is_subcontract' => true,
                'production_order_id' => $order->id,
                'source_type' => 'ProductionOrder',
                'date' => now()->toDateString(),
                'delivery_date' => now()->addDays($op->subcontract_lead_time_days ?? 0)->toDateString(),
                'status' => $status,
                'discount_type' => 'without_discount',
                'tax_type' => 'order_wise_tax',
                'gst_type' => 'cgst_sgst',
                'subtotal' => $policy['total_cost'],
                'discount_amount' => 0,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => $policy['total_cost'],
                'notes' => $sourceNote,
                'created_by' => $userId ?: 1,
            ]);

            $poi = PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $policy['service_product_id'],
                'production_order_id' => $order->id,
                'production_order_operation_id' => $op->id,
                'quantity' => $policy['quantity'],
                'rate' => $policy['unit_cost'],
                'amount' => $policy['total_cost'],
                'total_amount' => $policy['total_cost'],
            ]);

            $op->update([
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poi->id,
            ]);

            // Audit Trail Progress Log
            ProductionOrderProgressLog::create([
                'tenant_id' => $tenantId,
                'production_order_id' => $order->id,
                'operation_id' => $op->id,
                'quantity_completed' => 0,
                'recorded_at' => now(),
                'status' => $op->status,
                'log_type' => 'subcontract_procurement_created',
                'logged_by' => $userId ?: 1,
                'remarks' => "Subcontract PO #{$po->purchase_order_number} ({$status}) auto-generated for Op #{$op->sequence}.",
            ]);

            if ($status === 'Approved') {
                $this->poService->syncPrOrderedQtyOnApproval($po);
            }

            return $po;
        });
    }

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

            // Determine product to procure (subcontract service product, component product, or FG product)
            $productId = $op->subcontract_service_product_id ?? $op->source_product_id ?? $order->product_id;
            $qty = (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : ($order->quantity_ordered ?? 1.0));
            $unitCost = (float) ($op->subcontract_cost_per_unit ?? 0.0);

            // Check if PR already exists for this order/operation to prevent duplicate generation
            $existingPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->whereIn('source_type', ['mo', 'ProductionOrder'])
                ->where('source_id', $order->id)
                ->where('notes', 'like', "%Op #{$op->sequence}%")
                ->first();

            if (!$existingPr) {
                $existingPr = PurchaseRequisition::where('tenant_id', $tenantId)
                    ->whereIn('source_type', ['mo', 'ProductionOrder'])
                    ->where('source_id', $order->id)
                    ->first();
            }

            if ($existingPr) {
                return $existingPr;
            }

            $serviceNote = "Subcontract Service — Op #{$op->sequence} {$op->name} for Order #{$order->order_number} ({$order->product?->name})";

            // Create Requisition via standard PurchaseRequisitionService
            $payload = [
                'requisition_date' => now()->toDateString(),
                'source_type' => 'mo',
                'production_order_id' => $order->id,
                'notes' => $serviceNote,
                'items' => [
                    [
                        'product_id' => $productId,
                        'quantity' => $qty,
                        'estimated_cost' => $unitCost,
                    ]
                ],
            ];

            $pr = $this->prService->storeRequisition($payload, $tenantId);

            return $pr;
        });
    }
}
