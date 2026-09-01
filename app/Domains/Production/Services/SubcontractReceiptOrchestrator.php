<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
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
            $grnItems = GoodsReceiptNoteItem::where('goods_receipt_note_id', $grn->id)
                ->with('purchaseOrderItem')
                ->get();

            foreach ($grnItems as $grnItem) {
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

                if ($receivedQty <= 0) {
                    continue;
                }

                // Idempotency check: check if this GRN item was already processed for this operation
                $alreadyLogged = ProductionOrderProgressLog::where('tenant_id', $grn->tenant_id)
                    ->where('operation_id', $op->id)
                    ->where('remarks', 'like', "%Subcontract GRN #{$grn->grn_number}%")
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                // Determine batch continuity
                $batchId = $grnItem->production_batch_id ?? $op->order?->batches?->first()?->id;

                // Check if Quality Inspection is required
                $isQcRequired = $op->quality_required;
                if ($isQcRequired === null) {
                    $productId = $op->order?->product_id;
                    if ($productId) {
                        $isQcRequired = \App\Domains\Production\Models\ProductionQualityPlan::where('tenant_id', $grn->tenant_id)
                            ->where('product_id', $productId)
                            ->exists();
                    } else {
                        $isQcRequired = false;
                    }
                }
                $isQcRequired = (bool) $isQcRequired;

                if ($isQcRequired) {
                    // Check if pending QC inspection already exists for this GRN
                    $existingQc = ProductionQualityInspection::where('tenant_id', $grn->tenant_id)
                        ->where('production_order_operation_id', $op->id)
                        ->where('remarks', 'like', "%Subcontract GRN #{$grn->grn_number}%")
                        ->first();

                    if (!$existingQc) {
                        $this->qualityService->quickOperatorInspection(
                            tenantId: $grn->tenant_id,
                            data: [
                                'stage' => 'in_process',
                                'production_order_id' => $op->production_order_id,
                                'production_order_operation_id' => $op->id,
                                'batch_id' => $batchId,
                                'result' => 'pending',
                                'remarks' => "Subcontract GRN #{$grn->grn_number} receipt pending QC inspection.",
                            ]
                        );
                    }

                    $op->status = 'subcontract_qc_pending';
                    $op->save();
                    // Successor operation remains locked until QC PASS
                } else {
                    // Support partial & complete receipts
                    $targetQty = (float) ($op->target_produced_qty ?: ($op->order?->quantity_ordered ?: 0.0));
                    $newProducedQty = (float) $op->quantity_produced + $receivedQty;
                    $op->quantity_produced = $newProducedQty;

                    if ($op->material_supply_type === 'company_supplied') {
                        $this->materialBalanceService->backflushCompanyMaterial($grn->tenant_id, $op, $acceptedQty);
                    }

                    if ($targetQty > 0 && $newProducedQty < ($targetQty - 0.0001)) {
                        $op->status = ProductionOrderOperation::STATUS_RUNNING;
                    } else {
                        $op->status = ProductionOrderOperation::STATUS_COMPLETED;
                        $op->actual_end_time = now();

                        \App\Domains\Production\Models\ProductionScheduleOperation::where('production_order_operation_id', $op->id)
                            ->update([
                                'status' => \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_COMPLETED,
                                'actual_finish' => now(),
                            ]);
                    }
                    $op->save();

                    app(ProductionOrderService::class)->reconcileOperationReadiness($op->production_order_id);

                    // Ensure WIP record exists before transfer (preserving batch continuity)
                    $wip = ProductionWip::where('tenant_id', $op->tenant_id)
                        ->where('production_order_id', $op->production_order_id)
                        ->first();

                    if (!$wip) {
                        ProductionWip::create([
                            'tenant_id' => $op->tenant_id,
                            'production_order_id' => $op->production_order_id,
                            'production_batch_id' => $batchId,
                            'product_id' => $op->order?->product_id,
                            'current_routing_operation_id' => $op->routing_operation_id,
                            'current_work_center_id' => $op->work_center_id,
                            'quantity' => $op->order?->quantity_ordered ?? 0,
                            'available_quantity' => $newProducedQty,
                            'completed_quantity' => 0,
                            'rejected_quantity' => 0,
                            'scrap_quantity' => 0,
                            'rework_quantity' => 0,
                            'status' => 'active',
                        ]);
                    } else {
                        $wip->available_quantity = max((float)$wip->available_quantity, $newProducedQty);
                        if ($batchId && !$wip->production_batch_id) {
                            $wip->production_batch_id = $batchId;
                        }
                        $wip->save();
                    }

                    // Log progress for idempotency and audit trail
                    ProductionOrderProgressLog::create([
                        'tenant_id' => $grn->tenant_id,
                        'production_order_id' => $op->production_order_id,
                        'operation_id' => $op->id,
                        'production_batch_id' => $batchId,
                        'quantity_completed' => $receivedQty,
                        'recorded_at' => now(),
                        'status' => $op->status,
                        'log_type' => 'subcontract_receipt',
                        'logged_by' => auth()->id() ?: 1,
                        'remarks' => "Subcontract GRN #{$grn->grn_number} received {$receivedQty} units.",
                    ]);

                    // Evaluate and transfer WIP to successor operation
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
                $targetQty = (float) ($op->target_produced_qty ?: ($op->order?->quantity_ordered ?: 0.0));
                $acceptedQty = ($inspection->passed_qty && (float) $inspection->passed_qty > 0)
                    ? (float) $inspection->passed_qty
                    : ((float) ($inspection->inspected_quantity ?: ($op->order?->quantity_ordered ?: 0.0)));

                if ($op->material_supply_type === 'company_supplied') {
                    $this->materialBalanceService->backflushCompanyMaterial($inspection->tenant_id, $op, $acceptedQty);
                }

                $newProducedQty = max((float) $op->quantity_produced, $acceptedQty);
                $op->quantity_produced = $newProducedQty;

                if ($targetQty > 0 && $newProducedQty < ($targetQty - 0.0001)) {
                    $op->status = ProductionOrderOperation::STATUS_RUNNING;
                } else {
                    $op->status = ProductionOrderOperation::STATUS_COMPLETED;
                    $op->actual_end_time = now();

                    \App\Domains\Production\Models\ProductionScheduleOperation::where('production_order_operation_id', $op->id)
                        ->update([
                            'status' => \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_COMPLETED,
                            'actual_finish' => now(),
                        ]);
                }
                $op->save();

                app(ProductionOrderService::class)->reconcileOperationReadiness($op->production_order_id);

                $batchId = $inspection->batch_id ?? $op->order?->batches?->first()?->id;

                $wip = ProductionWip::where('tenant_id', $op->tenant_id)
                    ->where('production_order_id', $op->production_order_id)
                    ->first();

                if (!$wip) {
                    ProductionWip::create([
                        'tenant_id' => $op->tenant_id,
                        'production_order_id' => $op->production_order_id,
                        'production_batch_id' => $batchId,
                        'product_id' => $op->order?->product_id,
                        'current_routing_operation_id' => $op->routing_operation_id,
                        'current_work_center_id' => $op->work_center_id,
                        'quantity' => $newProducedQty,
                        'available_quantity' => $newProducedQty,
                        'completed_quantity' => 0,
                        'rejected_quantity' => 0,
                        'scrap_quantity' => 0,
                        'rework_quantity' => 0,
                        'status' => 'active',
                    ]);
                } else {
                    $wip->available_quantity = max((float)$wip->available_quantity, $newProducedQty);
                    if ($batchId && !$wip->production_batch_id) {
                        $wip->production_batch_id = $batchId;
                    }
                    $wip->save();
                }

                // Evaluate and transfer WIP to successor operation
                $this->wipService->evaluateAndExecuteWipTransfers($op->id);
            } else {
                $op->status = 'failed';
                $op->save();
            }
        });
    }
}
