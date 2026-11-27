<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Models\Tenant;

class SubcontractProcurementPolicyResolver
{
    /**
     * Resolve effective subcontract procurement workflow policy and validation rules.
     */
    public function resolvePolicy(ProductionOrderOperation $op, int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];

        // Safe backward-compatible default: manual_pr_po
        $workflowMode = $settings['subcontract_procurement_workflow'] ?? 'manual_pr_po';
        $autoApprovalLimit = isset($settings['subcontract_auto_approval_limit']) 
            ? (float) $settings['subcontract_auto_approval_limit'] 
            : 0.0;

        $order = $op->order ?? \App\Domains\Production\Models\ProductionOrder::find($op->production_order_id);
        $vendorId = $op->vendor_id;
        // C-06: the service is bought, never the finished good — a missing service product is a setup error, not a fallback
        $serviceProductId = $op->subcontract_service_product_id;
        // M-03: same yield rule as the routing cost — rate per GOOD unit = rate × 100 ÷ yield %.
        // The PO quantity stays the op's output target, so receipts of good units close the PO.
        $yield = (float) ($op->routingOperation?->expected_yield_percentage ?? 100);
        $unitCost = round((float) ($op->subcontract_cost_per_unit ?? 0.0) * ($yield > 0 ? 100 / $yield : 1), 4);
        $quantity = (float) ($op->target_produced_qty ?: ($order?->quantity_ordered ?: 0.0));
        $totalCost = round($quantity * $unitCost, 2);

        $errors = [];

        // 1. Validate Vendor
        if (!$vendorId) {
            $errors[] = "Subcontract vendor is unassigned on operation #{$op->sequence}.";
        } else {
            $vendor = Vendor::where('tenant_id', $tenantId)->find($vendorId);
            if (!$vendor || strtolower($vendor->status ?? 'active') === 'inactive') {
                $errors[] = "Subcontract vendor (ID: {$vendorId}) is missing or inactive.";
            }
        }

        // 2. Validate Service Product
        if (!$serviceProductId) {
            $errors[] = "Subcontract service product is unassigned on operation #{$op->sequence}.";
        } else {
            $product = Product::where('tenant_id', $tenantId)->find($serviceProductId);
            if (!$product || strtolower($product->status ?? 'active') === 'inactive') {
                $errors[] = "Service product (ID: {$serviceProductId}) is missing or inactive.";
            }
        }

        // 3. Evaluate Automatic Approval Safety
        $canAutoApprove = true;
        $fallbackReason = null;

        if ($workflowMode === 'auto_approved_po') {
            if (!empty($errors)) {
                $canAutoApprove = false;
                $fallbackReason = "Validation errors present: " . implode(' ', $errors);
            } elseif ($unitCost <= 0) {
                $canAutoApprove = false;
                $fallbackReason = "Subcontract unit cost is zero or unpriced.";
            } elseif ($autoApprovalLimit > 0 && $totalCost > $autoApprovalLimit) {
                $canAutoApprove = false;
                $fallbackReason = "Total PO cost ({$totalCost}) exceeds tenant auto-approval limit ({$autoApprovalLimit}).";
            }
        } else {
            $canAutoApprove = false;
        }

        // Determine effective strategy to execute
        $effectiveWorkflow = $workflowMode;
        if ($workflowMode === 'auto_approved_po' && !$canAutoApprove) {
            $effectiveWorkflow = 'auto_draft_po';
        }

        return [
            'workflow_mode' => $workflowMode,
            'effective_workflow' => $effectiveWorkflow,
            'can_auto_approve' => $canAutoApprove,
            'fallback_reason' => $fallbackReason,
            'vendor_id' => $vendorId,
            'service_product_id' => $serviceProductId,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'is_valid' => empty($errors),
            'validation_errors' => $errors,
        ];
    }
}
