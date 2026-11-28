<?php

namespace App\Domains\Production\Services;

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
            if (!$plan->isApproved()) {
                // QR-14: draft / pending / historical versions are never used for a new inspection
                throw new \InvalidArgumentException("Quality plan '{$plan->name}' v{$plan->version} is not approved and cannot be used for a new inspection.");
            }

            $order = !empty($data['production_order_id'])
                ? \App\Domains\Production\Models\ProductionOrder::withoutGlobalScopes()->find($data['production_order_id'])
                : null;

            $inspection = ProductionQualityInspection::create([
                'tenant_id' => $tenantId,
                'company_id' => $data['company_id'] ?? $order?->company_id ?? company_id(),
                'branch_id' => $data['branch_id'] ?? $order?->branch_id ?? branch_id(),
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

            // QR-14: only an Approved plan is used — the operation's own plan first, then the product's approved plan.
            // No "first plan of the tenant" fallback and no auto-created empty plans: without a plan the QC is quantity-only.
            $order = $orderId ? \App\Domains\Production\Models\ProductionOrder::find($orderId) : null;
            $planId = !empty($data['quality_plan_id'])
                ? ProductionQualityPlan::where('tenant_id', $tenantId)->approved()->whereKey($data['quality_plan_id'])->value('id')
                : null;
            if (!$planId && $orderOp) {
                $planId = app(OrderOperationQualityService::class)->checklistFor($orderOp)->first()?->quality_plan_id;
            }
            if (!$planId && $order) {
                $planId = ProductionQualityPlan::where('tenant_id', $tenantId)->approved()
                    ->where('type', ProductionQualityPlan::SCOPE_PRODUCT)
                    ->where('product_id', $orderOp?->source_product_id ?: $order->product_id)
                    ->value('id');
            }

            $inspection = ProductionQualityInspection::create([
                'tenant_id' => $tenantId,
                'company_id' => $data['company_id'] ?? $orderOp?->company_id ?? $orderOp?->order?->company_id ?? $order?->company_id ?? company_id(),
                'branch_id' => $data['branch_id'] ?? $orderOp?->branch_id ?? $orderOp?->order?->branch_id ?? $order?->branch_id ?? branch_id(),
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
                    $val = isset($res['value_numeric']) ? (float) $res['value_numeric'] : (float) ($res['value'] ?? 0);
                    if (($param->min_value !== null && $val < $param->min_value) ||
                        ($param->max_value !== null && $val > $param->max_value)) {
                        $passed = false;
                    }
                    $resultRow->update([
                        'recorded_value_numeric' => $val,
                        'result' => $passed ? 'passed' : 'failed',
                    ]);
                } elseif ($param->type === 'pass_fail') {
                    $passed = isset($res['value_pass']) ? (bool) $res['value_pass'] : (bool) ($res['value'] ?? true);
                    $resultRow->update([
                        'recorded_value_pass' => $passed,
                        'result' => $passed ? 'passed' : 'failed',
                    ]);
                } else {
                    $resultRow->update([
                        'recorded_value_text' => $res['value_text'] ?? ($res['value'] ?? null),
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

            // QF-03: no quantities are booked here — an outsourced operation's units are booked only by its shop-floor QC
            // (performOperationInspection → SubcontractReceiptService::bookAfterQc); the old PO-path hook is removed
            if ($inspection->production_order_operation_id) {
                if ($newResult === 'passed') {
                    app(ProductionWipService::class)->evaluateAndExecuteWipTransfers(
                        $inspection->production_order_operation_id,
                        $userId,
                        $inspection->batch_id
                    );
                    if ($inspection->production_order_id) {
                        app(\App\Domains\Production\Services\ProductionOrderService::class)->reconcileOperationReadiness($inspection->production_order_id);
                        app(\App\Domains\Production\Services\ProductionOrderService::class)->evaluateAndAutoCompleteOrder($inspection->production_order_id, $userId);
                    }
                }
            }
        });
    }

    /** QD: does this order operation need QC (order snapshot first, the routing for older orders)? */
    public function operationNeedsQc(\App\Domains\Production\Models\ProductionOrderOperation $op): bool
    {
        return (bool) ($op->quality_required || ($op->routingOperation?->quality_required ?? false));
    }

    /**
     * QD-02 / QD-03: the open (not yet performed) inspection record of a QC operation, created with the operation's
     * plans (order snapshot). A QC operation keeps one open record while it can still give units for QC: it is made
     * when the operation starts, when a subcontract receipt waits for QC, after each performed record and when the
     * operation's QC page is opened. $extra = true adds another open record by hand (QD-03).
     */
    public function openOperationInspection(\App\Domains\Production\Models\ProductionOrderOperation $op, ?int $userId = null, bool $extra = false): ?ProductionQualityInspection
    {
        if (!$this->operationNeedsQc($op)) {
            return null;
        }

        if (!$extra) {
            $open = ProductionQualityInspection::where('tenant_id', $op->tenant_id)
                ->where('production_order_operation_id', $op->id)
                ->where('status', 'draft')
                ->orderBy('id')
                ->first();
            if ($open) {
                return $open;
            }
            $waiting = app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($op->id);
            if ($op->status === \App\Domains\Production\Models\ProductionOrderOperation::STATUS_COMPLETED && $waiting <= 0.0001) {
                return null; // nothing more will come for QC
            }
        }

        return DB::transaction(function () use ($op, $userId) {
            $checklist = app(OrderOperationQualityService::class)->checklistFor($op);

            $inspection = ProductionQualityInspection::create([
                'tenant_id' => $op->tenant_id,
                'company_id' => $op->company_id ?? $op->order?->company_id ?? company_id(),
                'branch_id' => $op->branch_id ?? $op->order?->branch_id ?? branch_id(),
                'quality_plan_id' => $checklist->first()?->quality_plan_id,
                'inspection_number' => $this->nextInspectionNumber((int) $op->tenant_id),
                'stage' => 'in_process',
                'status' => 'draft',
                'result' => 'pending',
                'inspected_quantity' => 0,
                'passed_qty' => 0,
                'failed_qty' => 0,
                'production_order_id' => $op->production_order_id,
                'production_order_operation_id' => $op->id,
                'machine_id' => $op->machine_used_id ?? $op->machine_id,
                'operator_id' => $userId ?? auth()->id(),
            ]);

            foreach ($checklist as $link) {
                if (!$link->plan) {
                    continue;
                }
                \App\Domains\Production\Models\ProductionQualityInspectionPlan::firstOrCreate(
                    ['quality_inspection_id' => $inspection->id, 'quality_plan_id' => $link->plan->id],
                    ['tenant_id' => $inspection->tenant_id, 'is_mandatory' => (bool) $link->is_mandatory]
                );
            }

            return $inspection;
        });
    }

    /** INSP-YYYYMMDD-0001: next number of the day, per tenant (soft-deleted rows included, so numbers never repeat) */
    private function nextInspectionNumber(int $tenantId): string
    {
        $prefix = 'INSP-' . now()->format('Ymd') . '-';
        $last = ProductionQualityInspection::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('inspection_number', 'like', $prefix . '%')
            ->orderByDesc('inspection_number')
            ->value('inspection_number');
        $next = $last && preg_match('/-(\d+)$/', $last, $m) ? ((int) $m[1]) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * QD-04 (user 2026-11-28): perform an operation's open inspection record. One record = one fixed quantity with one
     * result: every check of the operation's plans is taken for that quantity; when every recorded check passes the
     * whole quantity passes, when any check fails the whole quantity fails. Units of another result go in another record
     * of the same operation. Passed units move on at once (output, WIP, next operation; on an outsourced operation the
     * waiting receipts are booked and get their GRN). Failed units: counted as rejected, NCR + Rework / Scrap as before
     * (the failed-quantity flow is to be discussed — QD open point).
     *
     * @param  array{quantity: float|int|string, parameter_values?: array, inspected_plans?: array, remarks?: ?string, batch_id?: ?int}  $data
     */
    public function performOperationInspection(ProductionQualityInspection $inspection, array $data, int $userId): ProductionQualityInspection
    {
        return DB::transaction(function () use ($inspection, $data, $userId) {
            $inspection = ProductionQualityInspection::whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            if ($inspection->status !== 'draft' || !$inspection->production_order_operation_id) {
                throw new \InvalidArgumentException("Inspection {$inspection->inspection_number} is already performed.");
            }

            $orderOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $inspection->tenant_id)
                ->findOrFail($inspection->production_order_operation_id);
            $orderId = $orderOp->production_order_id;
            $tenantId = (int) $inspection->tenant_id;
            $batchId = $data['batch_id'] ?? null;

            if (!$this->operationNeedsQc($orderOp)) {
                throw new \InvalidArgumentException("Operation {$orderOp->operation_number} does not need QC.");
            }

            $qty = round((float) ($data['quantity'] ?? 0), 4);
            if ($qty <= 0) {
                throw new \InvalidArgumentException('Enter the quantity this inspection covers.');
            }
            $waiting = app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($orderOp->id, $batchId);
            if ($waiting <= 0.0001) {
                throw new \InvalidArgumentException("No units of operation {$orderOp->operation_number} are waiting for QC. Output must be logged (or received from the vendor) first.");
            }
            if ($qty > $waiting + 0.0001) {
                throw new \InvalidArgumentException('The quantity (' . $this->qtyLabel($qty) . ') is more than the units waiting for QC (' . $this->qtyLabel($waiting) . ').');
            }

            // every check of the operation's plans, judged by the plan rules; mandatory checks must be filled
            $checklist = app(OrderOperationQualityService::class)->checklistFor($orderOp)
                ->map(fn ($link) => ['plan' => $link->plan, 'is_mandatory' => (bool) $link->is_mandatory])
                ->values();
            $evaluation = $this->evaluateChecklist(
                $checklist,
                (array) ($data['parameter_values'] ?? []),
                (array) ($data['inspected_plans'] ?? []),
                true
            );
            $failedChecks = collect($evaluation['results'])->filter(fn ($row) => !$row['passed']);
            if (empty($evaluation['plans'])) {
                // a QC operation with no plan attached: the inspector states the result for the quantity
                $stated = $data['result'] ?? null;
                if (!in_array($stated, ['passed', 'failed'], true)) {
                    throw new \InvalidArgumentException('This operation has no quality plan: choose whether the quantity passed or failed.');
                }
                $passed = $stated === 'passed';
            } else {
                $passed = $failedChecks->isEmpty();
            }

            $inspection->fill([
                'quality_plan_id' => $evaluation['plans'][0]['plan']->id ?? $inspection->quality_plan_id,
                'status' => 'approved',
                'result' => $passed ? 'passed' : 'failed',
                'inspected_quantity' => $qty,
                'passed_qty' => $passed ? $qty : 0,
                'failed_qty' => $passed ? 0 : $qty,
                'batch_id' => $batchId ?? $inspection->batch_id,
                'machine_id' => $inspection->machine_id ?? $orderOp->machine_used_id ?? $orderOp->machine_id,
                'remarks' => $data['remarks'] ?? null,
                'inspected_at' => now(),
                'audited_by' => $userId,
                'audited_at' => now(),
            ])->save();

            // the results of this performance replace any earlier rows (none for an open record)
            ProductionQualityInspectionResult::where('quality_inspection_id', $inspection->id)->delete();
            $this->storeChecklistResults($inspection, $evaluation);

            if ($passed) {
                $orderOp->quantity_produced += $qty;
            } else {
                $orderOp->quantity_rejected += $qty;
            }

            // the operation completes when the passed quantity reaches its target
            $targetQty = (float) ($orderOp->target_produced_qty > 0 ? $orderOp->target_produced_qty : ($orderOp->order->quantity_ordered ?? 0));
            if ($targetQty > 0 && (float) $orderOp->quantity_produced >= ($targetQty - 0.0001)) {
                $orderOp->status = \App\Domains\Production\Models\ProductionOrderOperation::STATUS_COMPLETED;
                $orderOp->actual_end_time = now();

                \App\Domains\Production\Models\ProductionScheduleOperation::where('production_order_operation_id', $orderOp->id)
                    ->update([
                        'status' => \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_COMPLETED,
                        'actual_finish' => now(),
                    ]);
            }
            $orderOp->save();

            // TF-10: QC of outsourced work — the waiting subcontract receipts are booked now and get their GRN
            if ($orderOp->is_external) {
                app(SubcontractReceiptService::class)->bookAfterQc($orderOp, $passed ? $qty : 0.0, $passed ? 0.0 : $qty, $userId);
            }

            // passed units move to the next operation
            if ($passed) {
                $effectiveBatchId = $batchId ?? $inspection->batch_id;
                $wip = ($effectiveBatchId && $orderOp->routing_operation_id) ? app(ProductionWipService::class)->getOrCreateWipForBatchOperation(
                    $orderId,
                    $effectiveBatchId,
                    $orderOp->routing_operation_id,
                    $userId
                ) : \App\Domains\Production\Models\ProductionWip::where('production_order_id', $orderId)->first();

                if ($wip) {
                    $wip->available_quantity += $qty;
                    $wip->completed_quantity += $qty;
                    $wip->save();
                }

                app(ProductionWipService::class)->evaluateAndExecuteWipTransfers($orderOp->id, $userId, $effectiveBatchId);
                app(\App\Domains\Production\Services\ProductionOrderService::class)->reconcileOperationReadiness($orderId);
                app(\App\Domains\Production\Services\ProductionOrderService::class)->evaluateAndAutoCompleteOrder($orderId, $userId);
            }

            // failed units (flow to be discussed): NCR + Rework / Scrap as before
            $ncr = null;
            if (!$passed) {
                $ncr = \App\Domains\Production\Models\ProductionNcr::create([
                    'tenant_id' => $tenantId,
                    'ncr_number' => 'NCR-SF-' . strtoupper(uniqid()),
                    'category' => 'process',
                    'status' => 'open',
                    'disposition_type' => 'pending',
                    'production_order_id' => $orderId,
                    'production_order_operation_id' => $orderOp->id,
                    'quality_inspection_id' => $inspection->id,
                    'batch_id' => $batchId,
                    'machine_id' => $orderOp->machine_used_id ?? $orderOp->machine_id,
                    'operator_id' => $userId,
                    'description' => "Inspection {$inspection->inspection_number} failed for " . $this->qtyLabel($qty) . " unit(s) on operation {$orderOp->operation_number}: " . $failedChecks->map(fn ($row) => $row['parameter']->name)->unique()->implode(', ') . '.',
                ]);

                app(ProductionNotificationService::class)->notifyNcrCreated($ncr);
                app(ProductionNotificationService::class)->notifyQualityHold($orderOp, $data['remarks'] ?? "Inspection {$inspection->inspection_number} failed.", $ncr->id);
            }

            $this->eventService->writeEvent($tenantId, [
                'production_order_id' => $orderId,
                'production_order_operation_id' => $orderOp->id,
                'operator_id' => $userId,
                'event_type' => 'Operation QC Inspection',
                'title' => 'Operation QC inspection performed',
                'description' => "Inspection {$inspection->inspection_number}: " . $this->qtyLabel($qty) . ' unit(s) ' . ($passed ? 'passed' : 'failed') . " on operation {$orderOp->operation_number}.",
                'severity' => $passed ? 'success' : 'warning',
                'event_source' => 'QualityInspectionService',
            ]);

            // QD-02: keep one open record while units can still come for QC
            $this->openOperationInspection($orderOp->fresh(), $userId);

            return $inspection;
        });
    }

    private function qtyLabel(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * QR-16 / QP-04 / QP-05: evaluate the submitted checklist against the plans.
     *  - mandatory plans are always inspected; optional plans only when ticked (inspected_plans[])
     *  - when $enforce is true, mandatory parameters of an inspected plan must have a value
     *  - numeric: outside min/max = failed; pass/fail: "fail" = failed; text: always passed
     *
     * @param  \Illuminate\Support\Collection  $checklist  items ['plan' => ProductionQualityPlan, 'is_mandatory' => bool]
     * @return array{plans: array, results: array, mandatory_failed: bool, failed_names: array<int, string>}
     */
    public function evaluateChecklist(\Illuminate\Support\Collection $checklist, array $values, array $inspectedPlanIds, bool $enforce): array
    {
        $inspectedPlanIds = array_map('intval', $inspectedPlanIds);
        $plans = [];
        $results = [];
        $mandatoryFailed = false;
        $failedNames = [];
        $missing = [];

        foreach ($checklist as $entry) {
            $plan = $entry['plan'];
            if (!$plan) {
                continue;
            }
            $planMandatory = (bool) $entry['is_mandatory'];
            if (!$planMandatory && !in_array((int) $plan->id, $inspectedPlanIds, true)) {
                continue; // optional plan not inspected this time
            }
            $plans[] = ['plan' => $plan, 'is_mandatory' => $planMandatory];
            $planValues = (array) ($values[$plan->id] ?? []);

            foreach ($plan->parameters as $param) {
                $raw = $planValues[$param->id] ?? null;
                $hasValue = $raw !== null && $raw !== '';

                if (!$hasValue) {
                    if ($param->is_mandatory) {
                        $missing[] = "{$plan->name}: {$param->name}";
                    }
                    continue;
                }

                $row = ['parameter' => $param, 'numeric' => null, 'pass' => null, 'text' => null, 'passed' => true];
                if ($param->type === \App\Domains\Production\Models\ProductionQualityPlanParameter::TYPE_NUMERIC) {
                    if (!is_numeric($raw)) {
                        $missing[] = "{$plan->name}: {$param->name} (not a number)";
                        continue;
                    }
                    $val = (float) $raw;
                    $row['numeric'] = $val;
                    $row['passed'] = !(($param->min_value !== null && $val < (float) $param->min_value) || ($param->max_value !== null && $val > (float) $param->max_value));
                } elseif ($param->type === \App\Domains\Production\Models\ProductionQualityPlanParameter::TYPE_PASS_FAIL) {
                    $row['pass'] = in_array(strtolower((string) $raw), ['pass', '1', 'true', 'yes'], true);
                    $row['passed'] = $row['pass'];
                } else {
                    $row['text'] = (string) $raw;
                }

                if (!$row['passed'] && $param->is_mandatory) {
                    $mandatoryFailed = true;
                    $failedNames[] = $param->name;
                }
                $results[] = $row;
            }
        }

        if ($enforce && !empty($missing)) {
            throw new \InvalidArgumentException('Enter every mandatory check before submitting: ' . implode('; ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? '…' : '') . '.');
        }

        return [
            'plans'            => $plans,
            'results'          => $results,
            'mandatory_failed' => $mandatoryFailed,
            'failed_names'     => array_values(array_unique($failedNames)),
        ];
    }

    /** Persist the evaluated checklist on an inspection: covered plans + one result row per recorded parameter. */
    public function storeChecklistResults(ProductionQualityInspection $inspection, array $evaluation): void
    {
        foreach ($evaluation['plans'] as $entry) {
            \App\Domains\Production\Models\ProductionQualityInspectionPlan::firstOrCreate(
                ['quality_inspection_id' => $inspection->id, 'quality_plan_id' => $entry['plan']->id],
                ['tenant_id' => $inspection->tenant_id, 'is_mandatory' => (bool) $entry['is_mandatory']]
            );
        }

        foreach ($evaluation['results'] as $row) {
            ProductionQualityInspectionResult::create([
                'tenant_id'                 => $inspection->tenant_id,
                'quality_inspection_id'     => $inspection->id,
                'quality_plan_parameter_id' => $row['parameter']->id,
                'recorded_value_numeric'    => $row['numeric'],
                'recorded_value_pass'       => $row['pass'],
                'recorded_value_text'       => $row['text'],
                'result'                    => $row['passed'] ? 'passed' : 'failed',
            ]);
        }
    }

    /**
     * QR-18: final QC of the order's finished product, done in the WIP section. Runs every approved product plan of
     * the product (A4, all mandatory). Only the accepted quantity can then be received into a warehouse.
     */
    public function recordFinalInspection(\App\Domains\Production\Models\ProductionOrder $order, array $data, ?int $userId = null): ProductionQualityInspection
    {
        return DB::transaction(function () use ($order, $data, $userId) {
            $gate = app(FinalQualityGateService::class);
            $plans = $gate->plans($order);
            if ($plans->isEmpty()) {
                throw new \InvalidArgumentException('This product has no approved quality plan, so no final QC is needed.');
            }

            $acceptedQty = max(0.0, (float) ($data['accepted_qty'] ?? 0));
            $rejectedQty = max(0.0, (float) ($data['rejected_qty'] ?? 0));
            $inspectedQty = $acceptedQty + $rejectedQty;
            if ($inspectedQty <= 0) {
                throw new \InvalidArgumentException('Inspected quantity (accepted + rejected) must be greater than zero.');
            }

            $pending = (float) $gate->summary($order)['pending'];
            if ($inspectedQty > $pending + 0.0001) {
                $fmt = rtrim(rtrim(number_format($pending, 4, '.', ''), '0'), '.');
                throw new \InvalidArgumentException("Only {$fmt} unit(s) are waiting for final QC.");
            }

            $checklist = $plans->map(fn ($plan) => ['plan' => $plan, 'is_mandatory' => true])->values();
            $evaluation = $this->evaluateChecklist(
                $checklist,
                (array) ($data['parameter_values'] ?? []),
                $plans->pluck('id')->all(),
                true
            );
            if ($evaluation['mandatory_failed'] && $rejectedQty <= 0 && empty($data['confirm_failed_accept'])) {
                throw new \InvalidArgumentException(
                    'A mandatory check failed (' . implode(', ', $evaluation['failed_names']) . '). Reject the affected units, or tick the confirmation to accept them anyway.'
                );
            }

            $result = ($rejectedQty > 0 || $evaluation['mandatory_failed'])
                ? ($acceptedQty > 0 ? 'partially_passed' : 'failed')
                : 'passed';

            $inspection = ProductionQualityInspection::create([
                'tenant_id' => $order->tenant_id,
                'company_id' => $order->company_id ?? company_id(),
                'branch_id' => $order->branch_id ?? branch_id(),
                'quality_plan_id' => $plans->first()->id,
                'inspection_number' => 'INSP-FQ-' . date('Ymd') . '-' . rand(1000, 9999),
                'stage' => 'final',
                'status' => 'approved',
                'result' => $result,
                'inspected_quantity' => $inspectedQty,
                'passed_qty' => $acceptedQty,
                'failed_qty' => $rejectedQty,
                'production_order_id' => $order->id,
                'production_order_operation_id' => null,
                'operator_id' => $userId ?? auth()->id(),
                'remarks' => $data['remarks'] ?? "Final QC in WIP: {$acceptedQty} accepted, {$rejectedQty} rejected.",
                'inspected_at' => now(),
                'audited_by' => $data['audited_by'] ?? $userId ?? auth()->id(),
                'audited_at' => now(),
            ]);

            $this->storeChecklistResults($inspection, $evaluation);

            if ($rejectedQty > 0) {
                $ncr = \App\Domains\Production\Models\ProductionNcr::create([
                    'tenant_id' => $order->tenant_id,
                    'ncr_number' => 'NCR-FQ-' . strtoupper(uniqid()),
                    'category' => 'process',
                    'status' => 'open',
                    'disposition_type' => 'pending',
                    'production_order_id' => $order->id,
                    'operator_id' => $userId ?? auth()->id(),
                    'description' => "Final QC rejected {$rejectedQty} finished unit(s) of order {$order->order_number}." . (!empty($data['defect_reason']) ? ' Reason: ' . $data['defect_reason'] : ''),
                ]);
                app(ProductionNotificationService::class)->notifyNcrCreated($ncr);
            }

            $this->eventService->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'operator_id' => $userId ?? auth()->id(),
                'event_type' => 'Final QC Inspection',
                'title' => 'Final QC Recorded in WIP',
                'description' => "Final QC: {$acceptedQty} accepted, {$rejectedQty} rejected.",
                'severity' => $result === 'passed' ? 'success' : 'warning',
                'event_source' => 'QualityInspectionService',
            ]);

            return $inspection;
        });
    }
}
