<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityInspectionResult;
use App\Domains\Production\Models\ProductionQualityPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QualityInspectionService
{
    public function __construct(
        private readonly NcrService $ncrService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create a Quality Inspection.
     */
    public function createInspection(int $tenantId, array $data): ProductionQualityInspection
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $plan = ProductionQualityPlan::where('tenant_id', $tenantId)->findOrFail($data['quality_plan_id']);

            $inspection = ProductionQualityInspection::create([
                'tenant_id' => $tenantId,
                'quality_plan_id' => $plan->id,
                'stage' => $data['stage'],
                'status' => 'draft',
                'result' => 'passed',
                'production_order_id' => $data['production_order_id'] ?? null,
                'production_order_operation_id' => $data['production_order_operation_id'] ?? null,
                'machine_id' => $data['machine_id'] ?? null,
                'operator_id' => $data['operator_id'] ?? null,
                'batch_id' => $data['batch_id'] ?? null,
                'serial_number_id' => $data['serial_number_id'] ?? null,
            ]);

            // Copy plan parameters as blank result rows
            foreach ($plan->parameters as $param) {
                ProductionQualityInspectionResult::create([
                    'tenant_id' => $tenantId,
                    'quality_inspection_id' => $inspection->id,
                    'quality_plan_parameter_id' => $param->id,
                    'result' => 'passed',
                ]);
            }

            $this->eventService->writeEvent($tenantId, [
                'production_order_id'           => $inspection->production_order_id,
                'production_order_operation_id' => $inspection->production_order_operation_id,
                'machine_id'                    => $inspection->machine_id,
                'operator_id'                   => $inspection->operator_id,
                'event_type'                    => 'Inspection Created',
                'title'                         => 'Quality Inspection Created',
                'description'                   => "Quality inspection #{$inspection->id} created for stage [{$inspection->stage}].",
                'severity'                      => 'info',
                'event_source'                  => 'QualityInspectionService',
            ]);

            return $inspection;
        });
    }

    /**
     * Create and auto-approve a quick inline operator quality inspection for an operation or batch.
     */
    public function quickOperatorInspection(int $tenantId, array $data, ?int $userId = null): ProductionQualityInspection
    {
        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $orderOpId = $data['production_order_operation_id'] ?? null;
            $orderOp = $orderOpId ? \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)->find($orderOpId) : null;
            $orderId = $data['production_order_id'] ?? ($orderOp?->production_order_id);
            $batchId = $data['batch_id'] ?? null;
            $result = $data['result'] ?? 'passed'; // passed, hold, failed
            $remarks = $data['remarks'] ?? 'Quick operator inspection completed.';

            // Look up existing quality plan for product or fallback
            $planId = $data['quality_plan_id'] ?? null;
            if (!$planId && $orderId) {
                $order = \App\Domains\Production\Models\ProductionOrder::find($orderId);
                $planId = ProductionQualityPlan::where('tenant_id', $tenantId)
                    ->where('product_id', $order?->product_id)
                    ->value('id');
            }

            if (!$planId) {
                $planId = ProductionQualityPlan::where('tenant_id', $tenantId)->value('id');
            }

            // Create quality plan if none exists for tenant
            if (!$planId) {
                $plan = ProductionQualityPlan::create([
                    'tenant_id' => $tenantId,
                    'name' => 'Standard In-Process Quality Plan',
                    'code' => 'QP-STD-' . rand(100, 999),
                    'type' => $data['stage'] ?? 'in_process',
                    'status' => 'approved',
                    'created_by' => $userId ?? auth()->id() ?? 1,
                ]);
                $planId = $plan->id;
            }

            $inspection = ProductionQualityInspection::create([
                'tenant_id' => $tenantId,
                'quality_plan_id' => $planId,
                'inspection_number' => 'INSP-OP-' . date('Ymd') . '-' . rand(1000, 9999),
                'stage' => $data['stage'] ?? 'in_process',
                'status' => ($result === 'pending') ? 'submitted' : 'approved',
                'result' => $result,
                'production_order_id' => $orderId,
                'production_order_operation_id' => $orderOpId,
                'machine_id' => $orderOp?->machine_used_id ?? $orderOp?->machine_id,
                'operator_id' => $userId ?? auth()->id(),
                'batch_id' => $batchId,
                'remarks' => $remarks,
                'inspected_at' => now(),
            ]);

            $this->eventService->writeEvent($tenantId, [
                'production_order_id' => $orderId,
                'production_order_operation_id' => $orderOpId,
                'operator_id' => $userId ?? auth()->id(),
                'event_type' => 'Quick Quality Check',
                'title' => 'Operator Quality Inspection Submitted',
                'description' => "Quality check result: [{$result}] for operation #{$orderOpId}.",
                'severity' => ($result === 'passed') ? 'info' : 'warning',
                'event_source' => 'QualityInspectionService',
            ]);

            return $inspection;
        });
    }

    /**
     * Record results for inspection parameters and evaluate pass/fail criteria.
     */
    public function recordResults(int $inspectionId, array $resultsData, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($inspectionId, $resultsData, $tenantId) {
            $inspection = ProductionQualityInspection::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($inspectionId);

            if (in_array($inspection->status, ['approved', 'closed'], true)) {
                throw new \InvalidArgumentException('Finalized inspections cannot be edited.');
            }

            $overallPassed = true;

            foreach ($resultsData as $res) {
                $resultRow = ProductionQualityInspectionResult::where('quality_inspection_id', $inspectionId)
                    ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                    ->where('quality_plan_parameter_id', $res['parameter_id'])
                    ->firstOrFail();

                $param = $resultRow->parameter;
                $passed = true;

                // Evaluate parameter specific rules
                if ($param->type === 'numeric') {
                    $val = (float) $res['value_numeric'];
                    if (($param->min_value !== null && $val < $param->min_value) ||
                        ($param->max_value !== null && $val > $param->max_value)) {
                        $passed = false;
                    }
                    $resultRow->update([
                        'recorded_value_numeric' => $val,
                        'result' => $passed ? 'passed' : 'failed',
                    ]);
                } elseif ($param->type === 'pass_fail') {
                    $passed = (bool) $res['value_pass'];
                    $resultRow->update([
                        'recorded_value_pass' => $passed,
                        'result' => $passed ? 'passed' : 'failed',
                    ]);
                } else {
                    $resultRow->update([
                        'recorded_value_text' => $res['value_text'],
                        'result' => 'passed',
                    ]);
                }

                if (! $passed) {
                    $overallPassed = false;
                }
            }

            $inspection->update([
                'result' => $overallPassed ? 'passed' : 'failed',
                'status' => 'submitted',
            ]);
        });
    }

    /**
     * Approve and finalize the inspection. Creates NCR automatically on failure.
     */
    public function approveInspection(int $inspectionId, int $userId, string $signature, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($inspectionId, $userId, $tenantId) {
            $inspection = ProductionQualityInspection::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($inspectionId);

            if ($inspection->status === 'approved') {
                return;
            }

            if ($inspection->status !== 'submitted') {
                throw new \InvalidArgumentException('Only submitted inspections can be approved.');
            }

            $user = \App\Models\User::find($userId);
            if ($user && !empty($user->role) && !in_array($user->role, ['admin', 'super_admin', 'production_manager', 'quality_inspector', 'quality_manager'], true)) {
                throw new \InvalidArgumentException("User #{$userId} with role '{$user->role}' is not authorized to approve quality inspections.");
            }

            $newResult = ($inspection->result === 'failed') ? 'failed' : 'passed';

            $inspection->update([
                'status' => 'approved',
                'result' => $newResult,
                'audited_by' => $userId,
                'audited_at' => Carbon::now(),
                'esignature' => hash('sha256', $userId.$inspectionId.'approved'.now()->timestamp),
            ]);

            // Timeline event integration
            $this->eventService->writeEvent($inspection->tenant_id, [
                'production_order_id' => $inspection->production_order_id,
                'machine_id' => $inspection->machine_id,
                'event_type' => 'Inspection Finalized',
                'title' => 'Quality Inspection Audited',
                'description' => "Inspection #{$inspection->id} finalized with result: ".strtoupper($newResult),
                'severity' => $newResult === 'passed' ? 'success' : 'warning',
                'event_source' => 'QualityInspectionService',
            ]);

            // Auto NCR creation if failed
            if ($newResult === 'failed') {
                $this->ncrService->createAutoNcr($inspection->id);
            }

            // Handle subcontract operation QC disposition
            if ($inspection->production_order_operation_id) {
                app(SubcontractReceiptOrchestrator::class)->processQcApproval($inspection);
            }

            // If inspection passed, clear quarantine status on receipts and transfer stock from Quarantine Warehouse to Main FG Warehouse
            if ($newResult === 'passed' && $inspection->production_order_id) {
                $quarantineWh = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $inspection->tenant_id)
                    ->where(function ($q) {
                        $q->where('code', 'QUARANTINE')->orWhere('name', 'LIKE', '%Quarantine%');
                    })->first();

                $quarantineWhId = $quarantineWh?->id;

                $quarantineReceipts = ProductionOrderReceipt::where('tenant_id', $inspection->tenant_id)
                    ->where('production_order_id', $inspection->production_order_id)
                    ->where(function ($q) use ($quarantineWhId) {
                        $q->where('quality_status', 'quarantine');
                        if ($quarantineWhId) {
                            $q->orWhere('warehouse_id', $quarantineWhId);
                        }
                    })
                    ->get();

                foreach ($quarantineReceipts as $quarantineReceipt) {
                    $executionService = app(ProductionExecutionService::class);
                    $mainWarehouseId = $executionService->defaultWarehouseId($inspection->tenant_id);

                    if ($mainWarehouseId && $quarantineReceipt->warehouse_id !== $mainWarehouseId) {
                        // Transfer stock out of Quality Quarantine Warehouse
                        StockService::recordOutflow(
                            $inspection->tenant_id,
                            $quarantineReceipt->product_id,
                            $quarantineReceipt->warehouse_id,
                            $quarantineReceipt->quantity_received,
                            'Quarantine Inspection Passed - Stock Transfer Out',
                            $inspection->production_order_id
                        );

                        $unitCost = (float) ($quarantineReceipt->product?->unit_cost ?? $quarantineReceipt->product?->cost_price ?? 0);

                        // Transfer stock into Main Finished Goods Warehouse
                        StockService::recordInflow(
                            $inspection->tenant_id,
                            $quarantineReceipt->product_id,
                            $mainWarehouseId,
                            $quarantineReceipt->quantity_received,
                            $unitCost,
                            'Quarantine Inspection Passed - Stock Transfer In to FG Warehouse',
                            $inspection->production_order_id
                        );
                    }

                    $quarantineReceipt->update([
                        'quality_status' => 'passed',
                        'warehouse_id'   => $mainWarehouseId ?: $quarantineReceipt->warehouse_id,
                    ]);
                }
            }
        });
    }
}
