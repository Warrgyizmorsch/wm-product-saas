<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use App\Domains\Purchase\Services\PurchaseOrderService;
use App\Domains\Purchase\Services\PurchaseRequisitionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Subcontract purchasing for ONE outsourced operation, raised only by the "Raise PR / PO" button on the order screen
 * or the MES floor (D-4 / TF-05 — nothing calls this automatically any more). What the button creates follows the
 * tenant setting (Production → Settings): manual_pr_po → a PR; auto_draft_po → a Draft PO; auto_approved_po → an
 * Approved PO within the auto-approval limit (else Draft).
 *
 * The PR / PO line is tied to the operation (N-14 / N-15), uses the operation's vendor, its service product and its
 * subcontract rate, and is a non-stock line posted to WIP 1204 when billed (TF-07).
 */
class SubcontractProcurementOrchestrator
{
    public function __construct(
        protected PurchaseRequisitionService $prService,
        protected PurchaseOrderService $poService,
        protected PurchaseOrderRepository $poRepo,
        protected SubcontractProcurementPolicyResolver $policyResolver
    ) {}

    /** Open PR / PO already raised for this operation, if any (cancelled ones never count — N-14). */
    public function existingDocument(ProductionOrderOperation $op, int $tenantId): PurchaseOrder|PurchaseRequisition|null
    {
        $poItem = PurchaseOrderItem::where('production_order_operation_id', $op->id)
            ->whereHas('order', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', '!=', 'Cancelled'))
            ->with('order')
            ->first();
        if ($poItem?->order) {
            return $poItem->order;
        }

        $prItem = PurchaseRequisitionItem::where('production_order_operation_id', $op->id)
            ->whereHas('requisition', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', '!=', 'Cancelled'))
            ->with('requisition')
            ->first();

        return $prItem?->requisition;
    }

    /**
     * The button. Returns the PR or PO it created, or the open one that already exists.
     *
     * @throws InvalidArgumentException when the operation is not ready to be bought (vendor, service product, rate…)
     */
    public function orchestrateSubcontractProcurement(ProductionOrderOperation $op, int $tenantId, ?int $userId = null): PurchaseOrder|PurchaseRequisition
    {
        if (!$op->is_external) {
            throw new InvalidArgumentException("Operation #{$op->operation_number} is not an outsourced operation.");
        }

        $order = $op->order ?? \App\Domains\Production\Models\ProductionOrder::find($op->production_order_id);
        if (!$order) {
            throw new InvalidArgumentException("Operation #{$op->operation_number} has no production order.");
        }
        if (in_array($order->status, ['cancelled', 'completed', 'closed'], true)) {
            throw new InvalidArgumentException("Order {$order->order_number} is {$order->status}: no subcontract purchasing can be raised.");
        }

        if ($existing = $this->existingDocument($op, $tenantId)) {
            return $existing;
        }

        $policy = $this->policyResolver->resolvePolicy($op, $tenantId);
        if (!$policy['is_valid']) {
            throw new InvalidArgumentException('Subcontract setup missing: ' . implode(' ', $policy['validation_errors']));
        }

        if ($policy['effective_workflow'] === 'manual_pr_po') {
            return $this->generateSubcontractRequisition($op, $tenantId, $userId);
        }

        return DB::transaction(function () use ($op, $order, $tenantId, $userId, $policy) {
            $status = ($policy['effective_workflow'] === 'auto_approved_po' && $policy['can_auto_approve']) ? 'Approved' : 'Draft';

            $note = "Production Subcontract · MO #{$order->order_number} · Op #{$op->sequence} ({$op->name})";
            if (!empty($policy['fallback_reason'])) {
                $note .= " [Auto-approval skipped: {$policy['fallback_reason']}]";
            }

            $po = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'purchase_order_number' => $this->poRepo->getNextPoNumber($tenantId),
                'vendor_id' => $policy['vendor_id'],
                'is_subcontract' => true,
                'production_order_id' => $order->id,
                'source_type' => 'ProductionOrder',
                'date' => now()->toDateString(),
                // N-18 / H-02: expected back = scheduled start + dispatch buffer + lead time + return buffer
                'delivery_date' => app(SubcontractDates::class)->expectedReturnDate($op)->toDateString(),
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
                'notes' => $note,
                'created_by' => $userId ?: (auth()->id() ?: 1),
            ]);

            $poi = PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $policy['service_product_id'],
                'line_type' => PurchaseOrderItem::LINE_TYPE_EXPENSE, // TF-07: service, not stock
                'chart_of_account_id' => $this->wipAccountId($tenantId),
                'production_order_id' => $order->id,
                'production_order_operation_id' => $op->id,
                'quantity' => $policy['quantity'],
                'rate' => $policy['unit_cost'],
                'amount' => $policy['total_cost'],
                'total_amount' => $policy['total_cost'],
            ]);

            $op->update(['purchase_order_id' => $po->id, 'purchase_order_item_id' => $poi->id]);

            if ($status === 'Approved') {
                $this->poService->syncPrOrderedQtyOnApproval($po);
            }

            $this->logEvent($op, $tenantId, $userId, "Subcontract PO {$po->purchase_order_number} ({$status}) raised for Op #{$op->sequence}.");

            return $po;
        });
    }

    /** A PR for the operation's service (manual_pr_po). An open PR for the same operation is returned, never a second. */
    public function generateSubcontractRequisition(ProductionOrderOperation $op, int $tenantId, ?int $userId = null): PurchaseRequisition
    {
        if (!$op->is_external) {
            throw new InvalidArgumentException("Operation {$op->name} is not an outsourced operation.");
        }
        $order = $op->order;
        if (!$order) {
            throw new InvalidArgumentException("Operation {$op->name} has no associated production order.");
        }

        $existing = $this->existingDocument($op, $tenantId);
        if ($existing instanceof PurchaseRequisition) {
            return $existing;
        }
        if ($existing instanceof PurchaseOrder) {
            throw new InvalidArgumentException("Subcontract PO {$existing->purchase_order_number} already exists for this operation.");
        }

        $policy = $this->policyResolver->resolvePolicy($op, $tenantId);
        if (!$policy['is_valid']) {
            throw new InvalidArgumentException('Subcontract setup missing: ' . implode(' ', $policy['validation_errors']));
        }

        return DB::transaction(function () use ($op, $order, $tenantId, $userId, $policy) {
            $pr = $this->prService->storeRequisition([
                'requisition_date' => now()->toDateString(),
                'expected_date' => app(SubcontractDates::class)->expectedReturnDate($op)->toDateString(),
                'source_type' => 'mo',
                'production_order_id' => $order->id,
                'notes' => "Subcontract Service — Op #{$op->sequence} {$op->name} for Order #{$order->order_number} ({$order->product?->name})",
                'items' => [[
                    'production_order_operation_id' => $op->id,
                    'product_id' => $policy['service_product_id'],
                    'quantity' => $policy['quantity'],
                    'estimated_cost' => $policy['unit_cost'],
                    'estimated_cost_is_base' => true, // the routing rate is already in base currency
                ]],
            ], $tenantId);

            $this->logEvent($op, $tenantId, $userId, "Subcontract PR {$pr->requisition_number} raised for Op #{$op->sequence}.");

            return $pr;
        });
    }

    private function wipAccountId(int $tenantId): ?int
    {
        return \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1204')->value('id');
    }

    private function logEvent(ProductionOrderOperation $op, int $tenantId, ?int $userId, string $text): void
    {
        app(ProductionEventService::class)->writeEvent($tenantId, [
            'production_order_id' => $op->production_order_id,
            'production_order_operation_id' => $op->id,
            'event_type' => 'Subcontract Procurement',
            'title' => 'Subcontract purchasing raised',
            'description' => mb_substr($text, 0, 250),
            'severity' => 'info',
            'event_source' => 'SubcontractProcurementOrchestrator',
            'triggered_by' => $userId,
        ]);
    }
}
