<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use Illuminate\Support\Facades\DB;

class SubcontractReceiptOrchestrator
{
    public function __construct(
        protected QualityInspectionService $qualityService,
        protected SubcontractMaterialBalanceService $materialBalanceService,
        protected ProductionWipService $wipService
    ) {}

    /**
     * Handle incoming GRN receipt for subcontract production operations.
     */
    public function processSubcontractReceipt(GoodsReceiptNote $grn): void
    {
        DB::transaction(function () use ($grn) {
            $grn->loadMissing(['items.purchaseOrderItem', 'vendor']);

            foreach ($grn->items as $grnItem) {
                $opId = $grnItem->production_order_operation_id ?? $grnItem->purchaseOrderItem?->production_order_operation_id;
                $orderId = $grnItem->production_order_id ?? $grn->production_order_id ?? $grnItem->purchaseOrderItem?->production_order_id;

                if (!$opId && !$orderId) {
                    continue;
                }

                $op = null;
                if ($opId) {
                    $op = ProductionOrderOperation::where('tenant_id', $grn->tenant_id)->find($opId);
                } elseif ($orderId) {
                    $op = ProductionOrderOperation::where('tenant_id', $grn->tenant_id)
                        ->where('production_order_id', $orderId)
                        ->where('is_external', true)
                        ->first();
                }

                if (!$op) {
                    continue;
                }

                $acceptedQty = (float) $grnItem->accepted_qty;
                $receivedQty = (float) $grnItem->received_qty;

                // Check if Quality Inspection is required
                $isQcRequired = (bool) $op->quality_required;
                if (!$isQcRequired) {
                    $hasPlan = \App\Domains\Production\Models\ProductionQualityPlan::where('tenant_id', $grn->tenant_id)
                        ->where('product_id', $op->order?->product_id)
                        ->exists();
                    $isQcRequired = $hasPlan;
                }

                if ($isQcRequired && $receivedQty > 0) {
                    // Create pending Quality Inspection
                    $inspection = $this->qualityService->quickOperatorInspection(
                        tenantId: $grn->tenant_id,
                        data: [
                            'stage' => 'in_process',
                            'production_order_id' => $op->production_order_id,
                            'production_order_operation_id' => $op->id,
                            'batch_id' => $grnItem->production_batch_id,
                            'result' => 'pending',
                            'remarks' => "Subcontract GRN #{$grn->grn_number} receipt pending QC inspection.",
                        ]
                    );

                    $op->status = 'subcontract_qc_pending';
                    $op->save();
                    // Successor OP30 remains locked until QC PASS
                } else {
                    // QC not required: complete operation and unlock successor
                    $op->quantity_produced = (float) $op->quantity_produced + $receivedQty;
                    if ($op->material_supply_type === 'company_supplied') {
                        $this->materialBalanceService->backflushCompanyMaterial($grn->tenant_id, $op, $acceptedQty);
                    }

                    $op->status = ProductionOrderOperation::STATUS_COMPLETED;
                    $op->save();

                    // Ensure WIP record exists before transfer
                    $hasWip = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $op->tenant_id)
                        ->where('production_order_id', $op->production_order_id)
                        ->exists();

                    if (!$hasWip) {
                        \App\Domains\Production\Models\ProductionWip::create([
                            'tenant_id' => $op->tenant_id,
                            'production_order_id' => $op->production_order_id,
                            'production_batch_id' => null,
                            'product_id' => $op->order?->product_id,
                            'current_routing_operation_id' => $op->routing_operation_id,
                            'current_work_center_id' => $op->work_center_id,
                            'quantity' => $op->order?->quantity_ordered ?? 0,
                            'available_quantity' => $op->order?->quantity_ordered ?? 0,
                            'completed_quantity' => 0,
                            'rejected_quantity' => 0,
                            'scrap_quantity' => 0,
                            'rework_quantity' => 0,
                            'status' => 'active',
                        ]);
                    }

                    // Evaluate and transfer WIP to successor operation (OP30) without creating Finished Goods
                    $this->wipService->evaluateAndExecuteWipTransfers($op->id);
                }
            }
        });
    }

    /**
     * Handle Quality Inspection Approval for a subcontracted operation.
     */
    public function processQcApproval(ProductionQualityInspection $inspection): void
    {
        if (!$inspection->production_order_operation_id) {
            return;
        }

        DB::transaction(function () use ($inspection) {
            $op = ProductionOrderOperation::where('tenant_id', $inspection->tenant_id)
                ->find($inspection->production_order_operation_id);

            if (!$op || !$op->is_external) {
                return;
            }

            if ($inspection->result === 'passed') {
                $acceptedQty = ($inspection->passed_qty && (float) $inspection->passed_qty > 0)
                    ? (float) $inspection->passed_qty
                    : ((float) ($inspection->inspected_quantity ?: ($op->order?->quantity_ordered ?: 0.0)));

                if ($op->material_supply_type === 'company_supplied') {
                    $this->materialBalanceService->backflushCompanyMaterial($inspection->tenant_id, $op, $acceptedQty);
                }

                $op->quantity_produced = $acceptedQty;
                $op->status = ProductionOrderOperation::STATUS_COMPLETED;
                $op->save();

                $wip = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $op->tenant_id)
                    ->where('production_order_id', $op->production_order_id)
                    ->first();

                if (!$wip) {
                    \App\Domains\Production\Models\ProductionWip::create([
                        'tenant_id' => $op->tenant_id,
                        'production_order_id' => $op->production_order_id,
                        'production_batch_id' => null,
                        'product_id' => $op->order?->product_id,
                        'current_routing_operation_id' => $op->routing_operation_id,
                        'current_work_center_id' => $op->work_center_id,
                        'quantity' => $acceptedQty,
                        'available_quantity' => $acceptedQty,
                        'completed_quantity' => 0,
                        'rejected_quantity' => 0,
                        'scrap_quantity' => 0,
                        'rework_quantity' => 0,
                        'status' => 'active',
                    ]);
                } else {
                    $wip->available_quantity = $acceptedQty;
                    $wip->save();
                }

                // Evaluate and transfer WIP to successor operation (OP30)
                $this->wipService->evaluateAndExecuteWipTransfers($op->id);
            } else {
                $op->status = 'failed';
                $op->save();
            }
        });
    }
}
