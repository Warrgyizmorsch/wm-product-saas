<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

use App\Domains\Production\Models\ProductionOrderOperationDependency;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Repositories\ProductionOrderRepositoryInterface;

class ProductionOrderService
{
    public function __construct(
        private readonly ProductionOrderNumberService $numberService,
        private readonly ProductionOrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Create a Production Order directly, evaluating any BOM item formulas using provided parameters.
     */
    public function create(array $data, ?int $userId = null): ProductionOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $productId = $data['product_id'];
            $product = Product::withoutGlobalScopes()->findOrFail($productId);
            $tenantId = $data['tenant_id'] ?? $product->tenant_id;

            $bomId = $data['bom_id'] ?? null;
            $bom = null;
            if ($bomId) {
                $bom = ProductionBom::withoutGlobalScopes()->with(['operationMaterials.material', 'items.material', 'product'])->find($bomId);
            } else {
                $bom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->whereIn('status', ['approved', 'active'])
                    ->with(['operationMaterials.material', 'items.material', 'product'])
                    ->first();
                $bomId = $bom?->id;
            }

            if ($bom) {
                app(ProductionBomService::class)->validateBomTreeForExecution($bom, $tenantId);
            }

            $branchId = $data['branch_id'] ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $companyId = $data['company_id'] ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();

            $order = ProductionOrder::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'order_number' => $data['order_number'] ?? $this->numberService->generateNextNumber($tenantId, $branchId),
                'product_id' => $productId,
                'bom_id' => $bomId,
                'project_id' => $data['project_id'] ?? null,
                'project_milestone_id' => $data['project_milestone_id'] ?? null,
                'project_task_id' => $data['project_task_id'] ?? null,
                'quantity_ordered' => $data['quantity_ordered'] ?? 1,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'end_date' => $data['end_date'] ?? now()->addDays(7)->toDateString(),
                'status' => $data['status'] ?? ProductionOrder::STATUS_RELEASED,
                'created_by' => $userId,
            ]);

            if ($bom) {
                $this->createOrderReservationsForBom($order, $bom, (float) $order->quantity_ordered, $tenantId, $order->parameters ?? []);
            }

            return $order;
        });
    }

    /**
     * Convert an approved Production Plan into a Production Order with frozen snapshots.
     */
    public function createFromPlan(int $planId, ?int $userId = null): ProductionOrder
    {
        return DB::transaction(function () use ($planId, $userId) {
            $plan = ProductionPlan::with(['requirements', 'operations'])->findOrFail($planId);

            // PX-21: a plan that was "released" but has no live order yet can still create its order
            $releasedWithoutOrder = $plan->status === ProductionPlan::STATUS_RELEASED
                && !ProductionOrder::where('production_plan_id', $plan->id)
                    ->where('status', '!=', ProductionOrder::STATUS_CANCELLED)
                    ->exists();

            if ($plan->status !== ProductionPlan::STATUS_APPROVED && $plan->status !== ProductionPlan::STATUS_MRP_GENERATED && !$releasedWithoutOrder) {
                throw new InvalidArgumentException('Only approved or MRP-generated production plans can be converted to production orders.');
            }

            $product = Product::find($plan->product_id);
            $productionMode = !empty($plan->production_mode)
                ? $plan->production_mode
                : ($product ? $product->getDefaultProductionMode() : 'standard');
            $hasExplicitModel = !empty($plan->production_model);
            $productionModel = $hasExplicitModel
                ? $plan->production_model
                : ProductionOrder::MODEL_PURE_MANUFACTURING;

            $branchId = $plan->branch_id ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $companyId = $plan->company_id ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();

            // PO-IMPL-04: manufactured products need a usable BOM + its Routing (same blocking rules as Create)
            $planBomId = $plan->bom_id;
            $planRoutingId = $plan->routing_id;
            $engineering = app(ProductionOrderEngineeringService::class);
            $resolution = null;
            if ($product && $engineering->requiresEngineering($product)) {
                $resolution = $engineering->resolveAndValidate($product, $plan->tenant_id, $planBomId ? (int) $planBomId : null, $planRoutingId ? (int) $planRoutingId : null);
                $engineering->assertCanCreate($resolution);
                $planBomId = $resolution['bom']?->id;
                $planRoutingId = $resolution['routing']?->id;
            }

            // 1. Create order header
            $order = ProductionOrder::create([
                'tenant_id' => $plan->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'order_number' => $this->numberService->generateNextNumber($plan->tenant_id, $branchId),
                'production_plan_id' => $plan->id,
                'product_id' => $plan->product_id,
                'bom_id' => $planBomId,
                'routing_id' => $planRoutingId,
                'sales_order_id' => $plan->sales_order_id,
                'sales_order_item_id' => $plan->sales_order_item_id,
                'quantity_ordered' => $plan->quantity,
                'start_date' => $plan->start_date,
                'end_date' => $plan->end_date,
                'production_mode' => $productionMode,
                'production_model' => $productionModel,
                'status' => ProductionOrder::STATUS_DRAFT,
                'created_by' => $userId,
            ]);

            // PX-21 (decision D-07 A): a manufactured product with BOM + routing is planned by the SAME engine as a direct order
            // (PO-06/07/08/12): one reservation + requisition line per product, only the chosen operations snapshotted.
            // The plan's requirement rows are already multi-level — cloning them AND exploding again doubled the material.
            $useDirectEngine = $resolution && $resolution['bom'] && $resolution['routing'];
            if ($useDirectEngine) {
                $planned = $engineering->computeMultiLevelPlan(
                    $resolution['bom'],
                    $resolution['selected_operation_ids'],
                    $resolution['selected_material_ids'],
                    (float) $plan->quantity,
                    $plan->tenant_id,
                    $order->branch_id
                );
                $this->persistPlannedMaterials($order, $planned['consolidated_materials']);
                $this->snapshotPlannedOperations($order, $planned['snapshot_tree_operations'], $userId, [
                    'stock' => $planned['stock_snapshot'],
                    'demands' => $planned['demands'],
                    'merges' => [],
                ]);
            }

            // 2. Clone Planning Requirements -> Order Reservations (products without BOM / routing only)
            $itemsToResolve = [];
            foreach ($useDirectEngine ? [] : $plan->requirements as $req) {
                $this->createMaterialReservation(
                    $order,
                    $req->bom_item_id,
                    $req->product_id,
                    (float) $req->required_quantity,
                    $req->uom_id ?? $req->product?->uom_id ?? 1
                );

                $itemsToResolve[] = [
                    'product_id' => $req->product_id,
                    'planned_qty' => (float) $req->required_quantity,
                    'uom_id' => $req->uom_id ?? $req->product?->uom_id ?? 1,
                    'child_bom_id' => null,
                ];
            }
            // UR-11 (M-23 / M-24): no requisition slip is created here any more — the user sends the material request(s) by hand
            // (sendMaterialRequest()); $itemsToResolve only documents what the plan needs.

            // 3. Clone Planning Operations -> Order Operations (snapshot)
            $createdOps = [];
            $bom = $order->bom ?? ProductionBom::withoutGlobalScopes()->find($order->bom_id);
            $routing = $order->routing ?? ($order->routing_id ? Routing::withoutGlobalScopes()->find($order->routing_id) : null);

            $hasChildBoms = $bom && $bom->operationMaterials()->whereNotNull('child_bom_id')->exists();

            if ($useDirectEngine) {
                // already built above by the direct-order engine
            } elseif ($hasChildBoms && $routing) {
                $this->snapshotMultiLevelRoutings($order, $bom, $routing, (float) $order->quantity_ordered, $order->tenant_id, $userId);
            } else {
                foreach ($plan->operations as $idx => $planOp) {
                $status = ($idx === 0) ? ProductionOrderOperation::STATUS_READY : ProductionOrderOperation::STATUS_WAITING;
                $routingOp = $planOp->routingOperation;

                $opTargetQty = (float) ($order->quantity_ordered ?? 1.0);
                $opSourceProductId = $order->product_id;

                if ($routingOp && $bom) {
                    if ((int) $bom->product_id === (int) $order->product_id) {
                        $routingMaterials = RoutingOperationMaterial::where('routing_operation_id', $routingOp->id)
                            ->where(fn ($q) => $q->where('bom_id', $bom->id)->orWhereNull('bom_id'))
                            ->where('is_alternative', false)
                            ->get();
                        if ($routingMaterials->isNotEmpty()) {
                            foreach ($routingMaterials as $mat) {
                                if ((int) $mat->material_id === (int) $order->product_id) {
                                    continue;
                                }
                                $baseQty = ($bom && (float) $bom->base_quantity > 0) ? (float) $bom->base_quantity : 1.0;
                                $ratio = (float) $mat->quantity / $baseQty;

                                if ($ratio <= 0) {
                                    $ratio = $this->calculateBomComponentRatio($bom, $mat->material_id);
                                }

                                if ($ratio !== null && $ratio > 0) {
                                    $calculatedTarget = round((float) $order->quantity_ordered * $ratio, 4);
                                    if ($calculatedTarget > 0) {
                                        $opTargetQty = $calculatedTarget;
                                        if ($idx < count($plan->operations) - 1) {
                                            $opSourceProductId = $mat->material_id;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                $processingTime = $planOp->processing_time_minutes > 0
                    ? (float) $planOp->processing_time_minutes
                    : ((float) ($routingOp?->processing_time_minutes ?? 0) * $opTargetQty);
                $totalTime = (float) $planOp->setup_time_minutes + $processingTime;

                $op = ProductionOrderOperation::create([
                    'tenant_id' => $order->tenant_id,
                    'production_order_id' => $order->id,
                    'routing_operation_id' => $planOp->routing_operation_id,
                    'source_product_id' => $opSourceProductId,
                    'source_bom_id' => $order->bom_id,
                    'source_routing_id' => $order->routing_id,
                    'bom_level' => 1,
                    'target_produced_qty' => $opTargetQty,
                    'is_intermediate' => ((int) $opSourceProductId !== (int) $order->product_id),
                    'sequence' => $planOp->sequence,
                    'operation_number' => $planOp->operation_number,
                    'name' => $planOp->name,
                    'work_center_id' => $planOp->work_center_id,
                    'section_id' => $routingOp?->section_id,
                    'machine_id' => $planOp->machine_id,
                    'section_manual_production_id' => $planOp->machine_id ? null : $routingOp?->section_manual_production_id,
                    'status' => $status,
                    'setup_time_planned' => $planOp->setup_time_minutes,
                    'processing_time_planned' => $processingTime,
                    'total_time_planned' => $totalTime,
                    'setup_time_actual' => 0.00,
                    'processing_time_actual' => 0.00,
                    'quantity_produced' => 0.0000,
                    'quantity_rejected' => 0.0000,
                    'quantity_scrapped' => 0.0000,
                    'is_external' => (bool) ($planOp->is_external ?? $routingOp?->is_external ?? false),
                    'vendor_id' => $planOp->vendor_id ?? $routingOp?->vendor_id,
                    'subcontract_lead_time_days' => (int) ($routingOp?->subcontract_lead_time_days ?? 0),
                    'subcontract_cost_per_unit' => (float) ($routingOp?->subcontract_cost_per_unit ?? 0.0),
                    'subcontract_service_product_id' => $routingOp?->subcontract_service_product_id,
                    'material_supply_type' => $routingOp?->material_supply_type ?? (in_array($order->production_model, ['complete_subcontracting', 'subcontract_complete']) ? 'vendor_supplied' : 'company_supplied'),
                    'subcontract_input_type' => $routingOp?->subcontract_input_type ?? 'bom_raw_materials',
                    'dispatch_buffer_days' => (int) ($routingOp?->dispatch_buffer_days ?? 0),
                    'return_buffer_days' => (int) ($routingOp?->return_buffer_days ?? 0),
                    'queue_threshold_enabled' => (bool) ($routingOp?->queue_threshold_enabled ?? $routingOp?->overlap_enabled ?? false),
                    'overlap_enabled' => (bool) ($routingOp?->queue_threshold_enabled ?? $routingOp?->overlap_enabled ?? false),
                    'transfer_batch_quantity' => (float) ($routingOp?->transfer_batch_quantity ?? 0.0000),
                    'transfer_lag_minutes' => (int) ($routingOp?->transfer_lag_minutes ?? 0),
                    'is_parallel' => (bool) ($routingOp?->is_parallel ?? false),
                    'parallel_group' => $routingOp?->parallel_group,
                    'parallel_type' => $routingOp?->parallel_type ?? 'AND',
                ]);
                $createdOps[] = $op;
            }

            // Bind operations dependency chain (previous_operation_id)
            for ($i = 1; $i < count($createdOps); $i++) {
                $currentOrderOp = $createdOps[$i];
                $rOp = $currentOrderOp->routingOperation;
                if ($rOp && $rOp->is_parallel) {
                    $currentOrderOp->previous_operation_id = null;
                    $currentOrderOp->status = ProductionOrderOperation::STATUS_READY;
                } elseif ($rOp && $rOp->previous_operation_id) {
                    $predOrderOp = collect($createdOps)->firstWhere('routing_operation_id', $rOp->previous_operation_id);
                    $currentOrderOp->previous_operation_id = $predOrderOp?->id;
                    if ($predOrderOp) {
                        \App\Domains\Production\Models\ProductionOrderOperationDependency::firstOrCreate([
                            'tenant_id' => $order->tenant_id,
                            'production_order_id' => $order->id,
                            'operation_id' => $currentOrderOp->id,
                            'predecessor_operation_id' => $predOrderOp->id,
                            'dependency_type' => 'FS',
                        ]);
                    }
                } else {
                    $currentOrderOp->previous_operation_id = $createdOps[$i - 1]->id;
                }
                $currentOrderOp->save();
            }
            }

            // Derive and save production model from snapshotted operations (routing operations are authoritative unless explicitly specified by caller)
            $currentModel = $order->production_model;
            if (empty($currentModel) || $currentModel === ProductionOrder::MODEL_PURE_MANUFACTURING) {
                $fallback = $product?->default_production_model ?? ProductionOrder::MODEL_PURE_MANUFACTURING;
                $derivedModel = ProductionOrder::determineProductionModelFromOperations($order->operations()->get(), $fallback);
                $order->production_model = $derivedModel;
                $order->save();
            }

            // Auto-orchestrate Subcontract Procurement according to tenant settings (Manual PR/PO, Auto Draft PO, Auto Approved PO)
            foreach ($createdOps as $createdOp) {
                if ($createdOp->is_external) {
                    try {
                        app(SubcontractProcurementOrchestrator::class)->orchestrateSubcontractProcurement($createdOp, $order->tenant_id, $userId);
                    } catch (\Throwable $e) {
                        // Procurement orchestration fallback
                    }
                }
            }

            // 4. Progress Production Plan status
            $plan->status = ProductionPlan::STATUS_RELEASED;
            $plan->save();

            ProductionOrderRequest::where('tenant_id', $order->tenant_id)
                ->where('production_plan_id', $plan->id)
                ->whereNull('production_order_id')
                ->update([
                    'production_order_id' => $order->id,
                    'status' => 'production-order-created',
                ]);

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Order Created',
                'title' => 'Production Order Created',
                'description' => "Production order {$order->order_number} has been created from plan.",
                'severity' => 'info',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId,
            ]);

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Material Reserved',
                'title' => 'Materials Reserved',
                'description' => "Materials reserved for production order {$order->order_number}.",
                'severity' => 'info',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId,
            ]);

            return $order;
        });
    }

    /**
     * Create a Production Order directly (without a prior Production Plan).
     */
    public function createDirect(array $data, int $tenantId, ?int $userId = null): ProductionOrder
    {
        return DB::transaction(function () use ($data, $tenantId, $userId) {
            $selectedRequest = null;
            if (! empty($data['production_order_request_id'])) {
                $selectedRequest = ProductionOrderRequest::where('tenant_id', $tenantId)
                    ->where('status', 'draft')
                    ->whereNull('production_order_id')
                    ->with(['materialRequirementItem.materialRequirement'])
                    ->lockForUpdate()
                    ->findOrFail($data['production_order_request_id']);

                $data['product_id'] = $selectedRequest->product_id;
                $data['quantity_ordered'] = $selectedRequest->quantity_requested;
                $data['sales_order_id'] = $selectedRequest->materialRequirementItem?->materialRequirement?->sales_order_id;
                $data['sales_order_item_id'] = $selectedRequest->materialRequirementItem?->sales_order_item_id;
            }

            $productId = $data['product_id'];
            $quantity = (float) $data['quantity_ordered'];

            // PO-IMPL-04 (PO-01 / PO-02 / PO-07): BOM → its Routing (never routings.product_id), blocking checks and
            // the Tab 1 selection, through the shared engineering service (same rules as the FormRequests).
            $branchId = $data['branch_id'] ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $product = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($productId);
            $engineering = app(ProductionOrderEngineeringService::class);
            $hasOps = array_key_exists('selected_operation_ids', $data);
            $hasMats = array_key_exists('selected_material_ids', $data);
            $requireExplicit = !empty($data['engineering_selection_submitted']) || $hasOps || $hasMats;
            $resolution = $engineering->resolveAndValidate(
                $product,
                $tenantId,
                !empty($data['bom_id']) ? (int) $data['bom_id'] : null,
                !empty($data['routing_id']) ? (int) $data['routing_id'] : null,
                $hasOps ? array_values((array) ($data['selected_operation_ids'] ?? [])) : null,
                $hasMats ? array_values((array) ($data['selected_material_ids'] ?? [])) : null,
                $requireExplicit
            );
            $engineering->assertCanCreate($resolution);
            $bom = $resolution['bom'];        // null for bought / traded products (PO-02)
            $routing = $resolution['routing'];
            $defaultMode = $product ? $product->getDefaultProductionMode() : 'standard';
            $productionMode = !empty($data['production_mode']) ? $data['production_mode'] : $defaultMode;
            $hasExplicitModel = !empty($data['production_model']);
            $productionModel = $hasExplicitModel
                ? $data['production_model']
                : ProductionOrder::MODEL_PURE_MANUFACTURING;

            $companyId = $data['company_id'] ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();

            $projectId = $data['project_id'] ?? null;
            $projectTaskId = $data['project_task_id'] ?? null;
            $projectMilestoneId = $data['project_milestone_id'] ?? null;

            if ($projectTaskId && ! $projectId) {
                $task = \App\Domains\Projects\Models\Task::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($projectTaskId);
                if ($task) {
                    $projectId = $task->project_id;
                    $projectMilestoneId = $projectMilestoneId ?? $task->milestone_id;
                }
            }

            $order = ProductionOrder::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'order_number' => $data['order_number'] ?? $this->numberService->generateNextNumber($tenantId, $branchId),
                'production_plan_id' => null,
                'product_id' => $productId,
                'bom_id' => $bom?->id,
                'routing_id' => $routing?->id,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'sales_order_item_id' => $data['sales_order_item_id'] ?? null,
                'project_id' => $projectId,
                'project_milestone_id' => $projectMilestoneId,
                'project_task_id' => $projectTaskId,
                'quantity_ordered' => $quantity,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'production_mode' => $productionMode,
                'production_model' => $productionModel,
                'status' => ProductionOrder::STATUS_DRAFT,
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);

            if ($order->project_id) {
                try {
                    $proj = $order->project ?: \App\Domains\Projects\Models\Project::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($order->project_id);
                    if ($proj) {
                        app(\App\Domains\Projects\Services\ActivityLogService::class)->record(
                            $proj,
                            'production.created',
                            "Production Order {$order->order_number} created from Project Task",
                            null,
                            $order->projectTask,
                            ['production_order_id' => $order->id]
                        );
                    }
                } catch (\Throwable $e) {
                    // Do not break order creation if activity logging fails
                }
            }

            if ($selectedRequest) {
                $selectedRequest->update([
                    'production_order_id' => $order->id,
                    'status' => 'production-order-created',
                ]);
            } elseif (! empty($data['sales_order_item_id'])) {
                $request = ProductionOrderRequest::where('tenant_id', $tenantId)
                    ->whereNull('production_order_id')
                    ->whereIn('status', ['draft', 'approved'])
                    ->whereHas('materialRequirementItem', function ($query) use ($data) {
                        $query->where('sales_order_item_id', $data['sales_order_item_id']);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($request) {
                    $request->update([
                        'production_order_id' => $order->id,
                        'status' => 'production-order-created',
                    ]);
                }
            }

            // PO-IMPL-04 (PO-08 / PO-10 / PO-12): multi-level plan from the chosen operations + materials —
            // one reservation and one requisition line per product (shared materials summed), and only the chosen
            // parent operations + the primary operations of exploded child BOMs are snapshotted.
            if ($bom && $routing) {
                $plan = $engineering->computeMultiLevelPlan(
                    $bom,
                    $resolution['selected_operation_ids'],
                    $resolution['selected_material_ids'],
                    $quantity,
                    $tenantId,
                    $order->branch_id
                );
                $this->persistPlannedMaterials($order, $plan['consolidated_materials']);
                $this->snapshotPlannedOperations($order, $plan['snapshot_tree_operations'], $userId, [
                    'stock' => $plan['stock_snapshot'],
                    'demands' => $plan['demands'],
                    'merges' => [],
                ]);
            } elseif (! $bom && config('production.temp_flow')) {
                // Temp no-BOM flow: operations come from the optional routing, no materials are planned.
                $this->snapshotTempFlowOperations($order, $routing, $userId);
            }

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Order Created',
                'title' => 'Production Order Created',
                'description' => "Production order {$order->order_number} has been created directly.",
                'severity' => 'info',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId,
            ]);

            if ($bom) app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Material Reserved',
                'title' => 'Materials Reserved',
                'description' => "Materials reserved for production order {$order->order_number}.",
                'severity' => 'info',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId,
            ]);

            return $order;
        });
    }

    /**
     * Release order to shop floor execution.
     */
    public function release(int $id, ?int $userId = null, bool $force = false): void
    {
        try {
            $order = $this->orderRepository->find($id);
            abort_if(!$order, 404, 'Production Order not found.');

            if (!$order->isPreRelease()) {
                throw new InvalidArgumentException('Only draft or locked orders can be released.');
            }

            // UR-12 (M-24): a SOFT rule — not having the material yet does not stop the release, it only has to be confirmed
            // ("The materials are not released. Do you still want to schedule it?"); on OK the order is released even with 0 issued.
            if (!$force && $this->materialsNotReleased($order)) {
                throw new MaterialsNotReleasedException(__('production.materials_not_released_confirm'));
            }

            $order->status = ProductionOrder::STATUS_RELEASED;
            $order->released_by = $userId;
            $order->released_at = now();
            $order->save();

            // Initialize Work-in-Progress (WIP) tracking
            app(ProductionWipService::class)->initializeWip($order->id, null, $userId);

            // PX-10: readiness is computed from release on (event), not when a page is opened
            $this->reconcileOperationReadiness($order);

            // Orchestrate subcontract procurement for external operations
            $externalOps = \App\Domains\Production\Models\ProductionOrderOperation::where('production_order_id', $order->id)
                ->where('is_external', true)
                ->get();

            foreach ($externalOps as $externalOp) {
                app(SubcontractProcurementOrchestrator::class)->orchestrateSubcontractProcurement($externalOp, $order->tenant_id, $userId);
            }

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Order Released',
                'title' => 'Production Order Released',
                'description' => "Production order {$order->order_number} released to the shop floor.",
                'severity' => 'success',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            // the soft "materials not released" stop is a normal question to the user, not a failure
            if (!($e instanceof MaterialsNotReleasedException)) {
                \Illuminate\Support\Facades\Log::error('Production order release failed: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Complete order execution.
     */
    public function complete(int $id, ?int $userId = null): void
    {
        $order = $this->orderRepository->find($id);
        abort_if(!$order, 404, 'Production Order not found.');

        if ($order->status !== ProductionOrder::STATUS_IN_PROGRESS && $order->status !== ProductionOrder::STATUS_RELEASED) {
            throw new InvalidArgumentException('Only orders in progress or released can be completed.');
        }

        // Validate that all operations are completed/skipped/cancelled
        $uncompletedOps = $order->operations()->whereNotIn('status', [
            ProductionOrderOperation::STATUS_COMPLETED,
            ProductionOrderOperation::STATUS_SKIPPED,
            ProductionOrderOperation::STATUS_CANCELLED,
        ])->exists();

        if ($uncompletedOps) {
            throw new InvalidArgumentException('Cannot complete order: There are operations that have not been completed, skipped, or cancelled.');
        }

        $order->status = ProductionOrder::STATUS_COMPLETED;
        $order->completed_by = $userId;
        $order->completed_at = now();
        $order->actual_end_date = now();
        $order->save();

        app(ProductionEventService::class)->writeEvent($order->tenant_id, [
            'production_order_id' => $order->id,
            'event_type' => 'Production Completed',
            'title' => 'Production Order Completed',
            'description' => "Production order {$order->order_number} completed.",
            'severity' => 'success',
            'event_source' => 'ProductionOrderService',
            'triggered_by' => $userId,
        ]);
    }

    /**
     * Automatically evaluate if all operations of an order are completed, and if so, auto-complete the order & schedules.
     */
    public function evaluateAndAutoCompleteOrder(ProductionOrder|int $orderOrId, ?int $userId = null): bool
    {
        $order = is_numeric($orderOrId) ? $this->orderRepository->find((int) $orderOrId) : $orderOrId;
        if (!$order || in_array($order->status, [ProductionOrder::STATUS_COMPLETED, ProductionOrder::STATUS_CLOSED])) {
            return false;
        }

        $ops = $order->operations;
        if ($ops->isEmpty()) {
            return false;
        }

        $allDone = true;
        foreach ($ops as $op) {
            $targetQty = (float) ($op->target_produced_qty ?: ($order->quantity_ordered ?: 0.0));
            $doneQty = (float) ($op->quantity_produced ?: 0.0);

            // Standalone SFG / Direct Sale Guardrail:
            // If a routing has Queue Threshold on its final operation, but the order is a standalone Finished Good
            // (or an SFG being produced directly for sale/stock with NO parent order consuming it),
            // the threshold is gracefully treated as a no-op for order closure. The order must NOT complete prematurely
            // on the transfer batch; it strictly requires 100% of the target ordered quantity to be produced.
            $isOpDone = ($op->status === ProductionOrderOperation::STATUS_SKIPPED)
                || ($op->status === ProductionOrderOperation::STATUS_CANCELLED)
                || ($targetQty > 0 && $doneQty >= ($targetQty - 0.0001))
                || ($op->status === ProductionOrderOperation::STATUS_COMPLETED && ($targetQty <= 0 || $doneQty >= ($targetQty - 0.0001)));

            if (!$isOpDone) {
                $allDone = false;
                break;
            }
        }

        if ($allDone) {
            foreach ($ops as $op) {
                if ($op->status !== ProductionOrderOperation::STATUS_COMPLETED) {
                    $op->status = ProductionOrderOperation::STATUS_COMPLETED;
                    $op->save();
                }
            }

            ProductionSchedule::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->where('status', '!=', ProductionSchedule::STATUS_COMPLETED)
                ->update([
                    'status' => ProductionSchedule::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

            ProductionScheduleOperation::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->where('status', '!=', ProductionScheduleOperation::STATUS_COMPLETED)
                ->update([
                    'status' => ProductionScheduleOperation::STATUS_COMPLETED,
                ]);

            $order->status = ProductionOrder::STATUS_COMPLETED;
            $order->completed_by = $userId ?: (auth()->id() ?: 1);
            $order->completed_at = now();
            $order->actual_end_date = $order->actual_end_date ?: now();
            $order->save();

            return true;
        }

        return false;
    }

    /**
     * Close order execution.
     */
    public function close(int $id, ?int $userId = null, ?string $noFinishedGoodsReason = null): void
    {
        $order = $this->orderRepository->find($id);
        abort_if(!$order, 404, 'Production Order not found.');

        if (! $order->isCompleted()) {
            throw new InvalidArgumentException('Only completed orders can be closed.');
        }

        // PX-19: an order is closed only after its finished goods are received — or with an explicit reason
        $received = $order->receivedFgQuantity();
        $pending = $order->pendingFgQuantity();
        $nothingReceived = $received <= 0.0;
        $reason = trim((string) $noFinishedGoodsReason);
        if (($nothingReceived || $pending > 0.0) && $reason === '') {
            throw new InvalidArgumentException(
                $nothingReceived
                    ? 'Cannot close this order: no finished goods have been received. Receive the finished goods first, or close with a reason.'
                    : "Cannot close this order: {$pending} units of finished goods are produced but not received. Receive them first, or close with a reason."
            );
        }
        if ($reason !== '') {
            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Closed Without Finished Goods',
                'title' => 'Order closed without (complete) finished goods receipt',
                'description' => "Received {$received}, pending {$pending}. Reason: {$reason}",
                'severity' => 'warning',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId,
            ]);
        }

        $order->status = ProductionOrder::STATUS_CLOSED;
        $order->closed_by = $userId;
        $order->closed_at = now();
        $order->save();

        app(ProductionEventService::class)->writeEvent($order->tenant_id, [
            'production_order_id' => $order->id,
            'event_type' => 'Production Closed',
            'title' => 'Production Order Closed',
            'description' => "Production order {$order->order_number} closed.",
            'severity' => 'info',
            'event_source' => 'ProductionOrderService',
            'triggered_by' => $userId,
        ]);
    }

    /**
     * Cancel order execution.
     */
    public function cancel(int $id, ?int $userId = null): void
    {
        $order = $this->orderRepository->find($id);
        abort_if(!$order, 404, 'Production Order not found.');

        if ($order->isClosed() || $order->isCompleted()) {
            throw new InvalidArgumentException('Closed or completed orders cannot be cancelled.');
        }

        DB::transaction(function () use ($order, $userId) {
            // PX-20 (decision D-06 A): material already issued to the order but not consumed by output goes back to stock
            // in the same action; the requisition slip and the WIP are closed with the order.
            $this->returnUnconsumedMaterial($order, $userId);

            $this->releaseInventoryReservations($order);

            \App\Domains\Production\Models\ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->update(['status' => 'cancelled']);
            \App\Domains\Production\Models\ProductionWip::withoutGlobalScopes()
                ->where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->update(['status' => 'cancelled']);

            $order->status = ProductionOrder::STATUS_CANCELLED;
            $order->save();

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Production Cancelled',
                'title' => 'Production Order Cancelled',
                'description' => "Production order {$order->order_number} has been cancelled.",
                'severity' => 'warning',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId,
            ]);

            // Cancel all operations
            $order->operations()->update(['status' => ProductionOrderOperation::STATUS_CANCELLED]);

            // Cancel associated schedule and operations
            $schedules = ProductionSchedule::withoutGlobalScopes()
                ->where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->get();
            foreach ($schedules as $schedule) {
                $schedule->update([
                    'status' => ProductionSchedule::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by' => $userId ?: auth()->id(),
                ]);
                $schedule->operations()->update(['status' => ProductionScheduleOperation::STATUS_CANCELLED]);
            }

            // Release plan back if applicable
            if ($order->production_plan_id) {
                $plan = ProductionPlan::find($order->production_plan_id);
                if ($plan) {
                    $plan->status = ProductionPlan::STATUS_APPROVED;
                    $plan->save();
                }
            }

            ProductionOrderRequest::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
                ->update(['status' => 'cancelled']);
        });
    }

    /**
     * Update order details (only allowed in draft state).
     */
    public function update(int $id, array $data): ProductionOrder
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->isFrozen()) {
            throw new InvalidArgumentException('Frozen orders cannot be modified.');
        }

        $order->update($data);

        return $order;
    }

    /**
     * Delete draft order.
     */
    public function delete(int $id): void
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->isFrozen()) {
            throw new InvalidArgumentException('Frozen orders cannot be deleted.');
        }

        DB::transaction(function () use ($order) {
            $this->releaseInventoryReservations($order);

            // Delete child snapshots
            $order->reservations()->delete();
            $order->operations()->delete();

            // Revert production plan if linked
            if ($order->production_plan_id) {
                $plan = ProductionPlan::find($order->production_plan_id);
                if ($plan) {
                    $plan->status = ProductionPlan::STATUS_APPROVED;
                    $plan->save();
                }
            }

            $order->delete();
        });
    }

    private function createMaterialReservation(
        ProductionOrder $order,
        ?int $bomItemId,
        int $productId,
        float $plannedQty,
        int $uomId,
        ?int $childBomId = null
    ): ?ProductionOrderReservation {
        // Consolidate reservation if already created for this order & product
        $existingRes = ProductionOrderReservation::where('production_order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingRes) {
            $existingRes->quantity_planned = round((float) $existingRes->quantity_planned + $plannedQty, 4);
            $existingRes->save();
            return $existingRes;
        }

        $warehouseId = $this->resolveReservationWarehouseId($order->tenant_id, $productId, $order->branch_id);

        $product = \App\Domains\Inventory\Models\Product::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->find($productId);

        $availableQty = 0.0;
        $effectiveReservationQty = $plannedQty;
        if ($product && ($product->type === 'semi_finished' || $childBomId)) {
            $availableQty = $warehouseId ? StockService::getAvailableStock($productId, $warehouseId) : 0.0;
            $effectiveReservationQty = min($plannedQty, $availableQty);
        }

        $reservation = ProductionOrderReservation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if ($reservation) {
            $reservation->quantity_planned = round((float) $reservation->quantity_planned + $effectiveReservationQty, 4);
            $reservation->save();
        } elseif ($effectiveReservationQty > 0 || !($product && ($product->type === 'semi_finished' || $childBomId))) {
            $reservation = ProductionOrderReservation::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'bom_item_id' => $bomItemId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_planned' => $effectiveReservationQty,
                'quantity_reserved' => 0.0000,
                'quantity_issued' => 0.0000,
                'uom_id' => $uomId,
            ]);
        }

        // If product is semi-finished or has child BOM, explode and create reservations for child components based on shortage
        if ($product && ($product->type === 'semi_finished' || $childBomId)) {
            $shortageQty = max(0.0, $plannedQty - $availableQty);

            if ($shortageQty > 0) {
                $subBom = null;
                if ($childBomId) {
                    $subBom = ProductionBom::withoutGlobalScopes()
                        ->where('tenant_id', $order->tenant_id)
                        ->where('id', $childBomId)
                        ->with(['operationMaterials.material', 'items.material'])
                        ->first();
                }
                if (!$subBom) {
                    $subBom = ProductionBom::withoutGlobalScopes()
                        ->where('tenant_id', $order->tenant_id)
                        ->where('product_id', $productId)
                        ->whereIn('status', ['approved', 'active'])
                        ->with(['operationMaterials.material', 'items.material'])
                        ->first();
                }

                $subRows = $subBom ? $this->primaryBomRows($subBom) : collect();
                if ($subBom && $subRows->isNotEmpty()) {
                    $baseQty = $subBom->base_quantity > 0 ? $subBom->base_quantity : 1.0;
                    $multiplier = $shortageQty / $baseQty;

                    foreach ($subRows as $subItem) {
                        if (!$subItem->material) continue;

                        $subPlannedQty = $this->resolveBomRowGrossQuantity($subItem) * $multiplier;

                        $this->createMaterialReservation(
                            $order,
                            $this->resolveBomRowBomItemId($subItem),
                            $subItem->material_id,
                            $subPlannedQty,
                            $subItem->uom_id ?? $uomId,
                            $subItem->child_bom_id
                        );
                    }
                }
            }
        }

        return $reservation ?? ProductionOrderReservation::where('production_order_id', $order->id)->first();
    }

    private function resolveReservationWarehouseId(int $tenantId, int $productId, ?int $branchId = null): ?int
    {
        // PO-IMPL-04 (PO-04): branch active standard warehouse with the most net stock, else the branch default
        $branchWarehouse = app(ProductionOrderEngineeringService::class)->resolvePrimaryBranchWarehouseId($tenantId, $productId, $branchId);
        if ($branchWarehouse) {
            return $branchWarehouse;
        }

        $stock = ProductWarehouseStock::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('available_qty', '>', 0)
            ->orderByDesc('available_qty')
            ->first();

        if ($stock) {
            return $stock->warehouse_id;
        }

        return Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->value('id')
            ?? Warehouse::query()->where('tenant_id', $tenantId)->value('id');
    }

    /**
     * PX-20: return the unconsumed part of the issued material (issued - estimated consumption of the output so far)
     * to the warehouse it was issued from.
     */
    private function returnUnconsumedMaterial(ProductionOrder $order, ?int $userId): void
    {
        $consumed = app(OperationMaterialService::class)->consumedEstimate($order);
        $materialService = app(ProductionMaterialService::class);

        foreach ($order->reservations()->get() as $reservation) {
            $issued = (float) $reservation->quantity_issued;
            if ($issued <= 0.0) {
                continue;
            }
            $back = round($issued - min($issued, (float) ($consumed[$reservation->product_id] ?? 0.0)), 4);
            if ($back <= 0.0) {
                continue;
            }
            $materialService->returnMaterial(
                (int) $reservation->id,
                $back,
                'Automatic return: production order ' . $order->order_number . ' cancelled',
                $userId ?: auth()->id(),
                $reservation->warehouse_id ? (int) $reservation->warehouse_id : null
            );
        }
    }

    private function releaseInventoryReservations(ProductionOrder $order): void
    {
        $order->loadMissing('reservations');

        foreach ($order->reservations as $reservation) {
            if (! $reservation->warehouse_id || $reservation->quantity_reserved <= 0) {
                continue;
            }

            StockService::releaseStock(
                $order->tenant_id,
                $reservation->product_id,
                $reservation->warehouse_id,
                (float) $reservation->quantity_reserved,
                'Production Order',
                $order->id,
                $reservation->id
            );

            $reservation->update(['quantity_reserved' => 0.0]);
        }
    }

    public function createAdHocRequisitionSlip(ProductionOrder $order, array $items, ?int $userId = null, ?string $notes = null): ProductionRequisitionSlip
    {
        return DB::transaction(function () use ($order, $items, $userId, $notes) {
            $branchId = $order->branch_id ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $companyId = $order->company_id ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();
            $year = now()->format('Y');
            $prefix = "MR-{$year}-";
            $lastSlip = ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $order->tenant_id)
                ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastSlip) {
                $lastNumStr = str_replace($prefix, '', $lastSlip->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            while (ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $order->tenant_id)
                ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                ->where('requisition_number', $reqNumber)
                ->exists()) {
                $nextNum++;
                $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            }

            $slipNotes = 'Ad-hoc Production Requirement for Order ' . $order->order_number;
            if (!empty($notes)) {
                $slipNotes .= ' — ' . $notes;
            }

            $slip = ProductionRequisitionSlip::create([
                'tenant_id' => $order->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'production_order_id' => $order->id,
                'requisition_number' => $reqNumber,
                'status' => 'pending',
                'requested_by' => $userId ?? auth()->id(),
                'requisition_date' => now()->toDateString(),
                'notes' => $slipNotes,
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (float) $item['quantity'];
                $itemNotes = $item['notes'] ?? null;

                $product = \App\Domains\Inventory\Models\Product::findOrFail($productId);
                $uomId = $product->uom_id ?? 1;

                ProductionRequisitionSlipItem::create([
                    'tenant_id' => $order->tenant_id,
                    'production_requisition_slip_id' => $slip->id,
                    'product_id' => $productId,
                    'quantity_planned' => $qty,
                    'quantity_reserved' => 0.0,
                    'quantity_issued' => 0.0,
                    'uom_id' => $uomId,
                ]);

                // Sync quantity_additional_requested on ProductionOrderReservation without altering quantity_planned
                $res = ProductionOrderReservation::where('tenant_id', $order->tenant_id)
                    ->where('production_order_id', $order->id)
                    ->where('product_id', $productId)
                    ->first();

                if (!$res) {
                    ProductionOrderReservation::create([
                        'tenant_id' => $order->tenant_id,
                        'production_order_id' => $order->id,
                        'product_id' => $productId,
                        'uom_id' => $uomId,
                        'quantity_planned' => 0.0,
                        'quantity_additional_requested' => $qty,
                        'quantity_reserved' => 0.0,
                        'quantity_issued' => 0.0,
                    ]);
                } else {
                    $res->quantity_additional_requested += $qty;
                    $res->save();
                }
            }

            // Write timeline event
            if (class_exists(ProductionEventService::class)) {
                app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                    'production_order_id' => $order->id,
                    'event_type'          => 'material_requested',
                    'title'               => 'Ad-hoc Material Requisition Created',
                    'description'         => "Ad-hoc material requisition {$reqNumber} created (" . count($items) . " items).",
                    'severity'            => 'info',
                    'triggered_by'        => $userId ?? auth()->id(),
                ]);
            }

            // UR-12: any request sent to the store — this one too — locks a draft order
            $this->lockOnFirstRequest($order, $userId);

            return $slip;
        });
    }

    private function createRequisitionSlip(ProductionOrder $order, array $itemsToResolve): void
    {
        $slip = $this->newRequisitionSlip($order);

        foreach ($itemsToResolve as $item) {
            $this->createRequisitionSlipItemsRecursively(
                $slip,
                $item['product_id'],
                $item['planned_qty'],
                $item['uom_id'],
                $item['child_bom_id'] ?? null
            );
        }
    }

    private function newRequisitionSlip(ProductionOrder $order, ?string $notes = null, ?int $requestedBy = null): ProductionRequisitionSlip
    {
        $branchId = $order->branch_id ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
        $companyId = $order->company_id ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();
        $year = now()->format('Y');
        $prefix = "MR-{$year}-";
        $lastSlip = ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
            ->where('requisition_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();
        $nextNum = 1;
        if ($lastSlip) {
            $lastNumStr = str_replace($prefix, '', $lastSlip->requisition_number);
            $nextNum = ((int) $lastNumStr) + 1;
        }
        $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
        while (ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
            ->where('requisition_number', $reqNumber)
            ->exists()) {
            $nextNum++;
            $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
        }

        $slip = ProductionRequisitionSlip::create([
            'tenant_id' => $order->tenant_id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'production_order_id' => $order->id,
            'requisition_number' => $reqNumber,
            'status' => 'pending',
            'requested_by' => $requestedBy,
            'requisition_date' => now()->toDateString(),
            'notes' => $notes ?? ('Generated automatically from Production Order ' . $order->order_number),
        ]);

        return $slip;
    }

    private function createRequisitionSlipItemsRecursively(
        ProductionRequisitionSlip $slip,
        int $productId,
        float $plannedQty,
        int $uomId,
        ?int $childBomId = null
    ): void {
        $warehouseId = $this->resolveReservationWarehouseId($slip->tenant_id, $productId, $slip->branch_id);
        $availableQty = $warehouseId ? StockService::getAvailableStock($productId, $warehouseId) : 0.0;

        // Fetch product
        $product = \App\Domains\Inventory\Models\Product::withoutGlobalScopes()
            ->where('tenant_id', $slip->tenant_id)
            ->find($productId);

        if ($product && $product->type === 'semi_finished') {
            $sfgToRequest = min($plannedQty, $availableQty);

            // Only create requisition slip item for available semi-finished stock in store
            if ($sfgToRequest > 0) {
                $this->createRequisitionSlipItem($slip, $productId, $sfgToRequest, $uomId, $warehouseId);
            }

            if ($availableQty >= $plannedQty) {
                return;
            }

            // Explode the shortage quantity to child BOM components
            $shortageQty = max(0.0, $plannedQty - $availableQty);
            
            if ($childBomId) {
                $subBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $slip->tenant_id)
                    ->where('id', $childBomId)
                    ->with(['operationMaterials.material', 'items.material'])
                    ->first();
            } else {
                $subBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $slip->tenant_id)
                    ->where('product_id', $productId)
                    ->whereIn('status', ['approved', 'active'])
                    ->with(['operationMaterials.material', 'items.material'])
                    ->first();
            }

            $subRows = $subBom ? $this->primaryBomRows($subBom) : collect();
            if ($subBom && $subRows->isNotEmpty()) {
                $baseQty = $subBom->base_quantity > 0 ? $subBom->base_quantity : 1.0;
                $multiplier = $shortageQty / $baseQty;

                foreach ($subRows as $subItem) {
                    if (!$subItem->material) continue;

                    $subPlannedQty = $this->resolveBomRowGrossQuantity($subItem) * $multiplier;

                    $this->createRequisitionSlipItemsRecursively(
                        $slip,
                        $subItem->material_id,
                        $subPlannedQty,
                        $subItem->uom_id ?? $uomId,
                        $subItem->child_bom_id
                    );
                }
                return;
            }
        }

        // Default: Create standard requisition item
        $this->createRequisitionSlipItem($slip, $productId, $plannedQty, $uomId, $warehouseId);
    }

    private function createRequisitionSlipItem(
        ProductionRequisitionSlip $slip,
        int $productId,
        float $plannedQty,
        ?int $uomId,
        ?int $warehouseId
    ): ProductionRequisitionSlipItem {
        $existingItem = ProductionRequisitionSlipItem::where('tenant_id', $slip->tenant_id)
            ->where('production_requisition_slip_id', $slip->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingItem) {
            $existingItem->quantity_planned = round((float) $existingItem->quantity_planned + $plannedQty, 4);
            $existingItem->save();
            return $existingItem;
        }

        $resolvedUomId = ($uomId && $uomId > 0)
            ? $uomId
            : (\App\Domains\Inventory\Models\Product::withoutGlobalScopes()->where('tenant_id', $slip->tenant_id)->where('id', $productId)->value('uom_id') ?? 1);

        return ProductionRequisitionSlipItem::create([
            'tenant_id' => $slip->tenant_id,
            'production_requisition_slip_id' => $slip->id,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_planned' => $plannedQty,
            'quantity_reserved' => 0.0000,
            'quantity_issued' => 0.0000,
            'uom_id' => $resolvedUomId,
        ]);
    }

    /**
     * Recursively snapshot multi-level routing operations and cross-assembly dependencies into the order.
     */
    public function snapshotMultiLevelRoutings(
        ProductionOrder $order,
        ProductionBom $topBom,
        Routing $topRouting,
        float $orderQuantity,
        int $tenantId,
        ?int $userId = null
    ): void {
        $visited = [];
        $createdComponentOps = [];
        $this->buildMultiLevelSnapshot(
            $order,
            $topBom->product_id,
            $topBom,
            $topRouting,
            $orderQuantity,
            1, // bom_level
            false, // is_intermediate
            $tenantId,
            $visited,
            $createdComponentOps,
            $userId
        );

        $this->reconcileOperationReadiness($order);

        // Derive and save production model from snapshotted operations (routing operations are authoritative unless explicitly specified by caller)
        $currentModel = $order->production_model;
        if (empty($currentModel) || $currentModel === ProductionOrder::MODEL_PURE_MANUFACTURING) {
            $fallback = $order->product?->default_production_model ?? ProductionOrder::MODEL_PURE_MANUFACTURING;
            $derivedModel = ProductionOrder::determineProductionModelFromOperations($order->operations()->get(), $fallback);
            $order->production_model = $derivedModel;
            $order->save();
        }
    }

    protected function buildMultiLevelSnapshot(
        ProductionOrder $order,
        int $productId,
        ProductionBom $bom,
        Routing $routing,
        float $targetQty,
        int $level,
        bool $isIntermediate,
        int $tenantId,
        array &$visited,
        array &$createdComponentOps,
        ?int $userId = null
    ): array {
        if (isset($createdComponentOps[$productId])) {
            return $createdComponentOps[$productId];
        }

        if (isset($visited[$productId])) {
            throw new InvalidArgumentException("Circular dependency loop detected in BOM/Routing for product ID {$productId}.");
        }
        $visited[$productId] = true;

        // 1. Snapshot operations for THIS routing node (FG or SFG)
        $createdOps = [];
        // PO-IMPL-04 (PO-10): automatic expansion uses primary operations + primary materials only
        foreach ($routing->operations->where('is_alternative', false)->values() as $idx => $routingOp) {
            $status = ($idx === 0) ? ProductionOrderOperation::STATUS_READY : ProductionOrderOperation::STATUS_WAITING;
            $primaryMaterialIds = RoutingOperationMaterial::withoutGlobalScopes()
                ->where('routing_operation_id', $routingOp->id)
                ->where('bom_id', $bom->id)
                ->where('is_alternative', false)
                ->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

            $opTargetQty = $targetQty;
            $opSourceProductId = $productId;

            // Apply RoutingOperationMaterial target scaling ONLY when snapshot node is the Master FG Product
            // and material is a component (not raw material input or FG product).
            if ((int) $productId === (int) $order->product_id) {
                $routingMaterials = RoutingOperationMaterial::where('routing_operation_id', $routingOp->id)
                    ->where(fn ($q) => $q->where('bom_id', $bom->id)->orWhereNull('bom_id'))
                    ->where('is_alternative', false)
                    ->get();
                if ($routingMaterials->isNotEmpty()) {
                    foreach ($routingMaterials as $mat) {
                        if ((int) $mat->material_id === (int) $order->product_id) {
                            continue;
                        }
                        $baseQty = ($bom && (float) $bom->base_quantity > 0) ? (float) $bom->base_quantity : 1.0;
                        $ratio = (float) $mat->quantity / $baseQty;

                        if ($ratio <= 0) {
                            $ratio = $this->calculateBomComponentRatio($bom, $mat->material_id);
                        }

                        if ($ratio !== null && $ratio > 0) {
                            $calculatedTarget = round($targetQty * $ratio, 4);
                            if ($calculatedTarget > 0) {
                                $opTargetQty = $calculatedTarget;
                                // opSourceProductId remains $productId for the routing being snapshotted
                                break;
                            }
                        }
                    }
                }
            }

            $processingTime = ($routingOp->processing_time_minutes * $opTargetQty);
            $totalTime = $routingOp->setup_time_minutes + $processingTime;

            $op = ProductionOrderOperation::create([
                'tenant_id' => $tenantId,
                'production_order_id' => $order->id,
                'routing_operation_id' => $routingOp->id,
                'selected_routing_material_ids' => $primaryMaterialIds,
                'source_product_id' => $opSourceProductId,
                'source_bom_id' => $bom->id,
                'source_routing_id' => $routing->id,
                'bom_level' => $level,
                'target_produced_qty' => $opTargetQty,
                'is_intermediate' => ((int) $opSourceProductId !== (int) $order->product_id) || $isIntermediate,
                'quantity_claimed' => 0.0000,
                'sequence' => $routingOp->sequence,
                'operation_number' => $routingOp->operation_number,
                'name' => $routingOp->name,
                'work_center_id' => $routingOp->work_center_id,
                'section_id' => $routingOp->section_id,
                'machine_id' => $routingOp->machine_id,
                'section_manual_production_id' => $routingOp->section_manual_production_id,
                'status' => $status,
                'setup_time_planned' => $routingOp->setup_time_minutes,
                'processing_time_planned' => $processingTime,
                'total_time_planned' => $totalTime,
                'setup_time_actual' => 0.00,
                'processing_time_actual' => 0.00,
                'quantity_produced' => 0.0000,
                'quantity_rejected' => 0.0000,
                'quantity_scrapped' => 0.0000,
                'is_external' => (bool) ($routingOp->is_external ?? false),
                'quality_required' => (bool) ($routingOp->quality_required ?? false),
                'vendor_id' => $routingOp->vendor_id,
                'subcontract_lead_time_days' => (int) ($routingOp->subcontract_lead_time_days ?? 0),
                'subcontract_cost_per_unit' => (float) ($routingOp->subcontract_cost_per_unit ?? 0.0),
                'subcontract_service_product_id' => $routingOp->subcontract_service_product_id,
                'material_supply_type' => $routingOp->material_supply_type ?? (in_array($order->production_model, ['complete_subcontracting', 'subcontract_complete']) ? 'vendor_supplied' : 'company_supplied'),
                'subcontract_input_type' => $routingOp->subcontract_input_type ?? 'bom_raw_materials',
                'dispatch_buffer_days' => (int) ($routingOp->dispatch_buffer_days ?? 0),
                'return_buffer_days' => (int) ($routingOp->return_buffer_days ?? 0),
                'queue_threshold_enabled' => (bool) ($routingOp->queue_threshold_enabled ?? $routingOp->overlap_enabled ?? false),
                'overlap_enabled' => (bool) ($routingOp->queue_threshold_enabled ?? $routingOp->overlap_enabled ?? false),
                'transfer_batch_quantity' => (float) ($routingOp->transfer_batch_quantity ?? 0.0000),
                'transfer_lag_minutes' => (int) ($routingOp->transfer_lag_minutes ?? 0),
                'is_parallel' => (bool) ($routingOp->is_parallel ?? false),
                'parallel_group' => $routingOp->parallel_group,
                'parallel_type' => $routingOp->parallel_type ?? 'AND',
            ]);
            $createdOps[] = $op;
        }

        // Bind intra-routing dependencies (previous_operation_id) within each routing branch
        $groupedOps = collect($createdOps)->groupBy(fn($op) => $op->source_routing_id ?: $op->source_product_id);
        foreach ($groupedOps as $branchOps) {
            $sortedOps = $branchOps->sortBy('sequence')->values();
            for ($i = 1; $i < count($sortedOps); $i++) {
                $currentOrderOp = $sortedOps[$i];
                $rOp = $currentOrderOp->routingOperation;
                if ($rOp && $rOp->is_parallel) {
                    $currentOrderOp->previous_operation_id = null;
                    $currentOrderOp->status = ProductionOrderOperation::STATUS_READY;
                } elseif ($rOp && $rOp->previous_operation_id) {
                    $predOrderOp = $sortedOps->firstWhere('routing_operation_id', $rOp->previous_operation_id);
                    $currentOrderOp->previous_operation_id = $predOrderOp?->id;
                    if ($predOrderOp) {
                        \App\Domains\Production\Models\ProductionOrderOperationDependency::firstOrCreate([
                            'tenant_id' => $tenantId,
                            'production_order_id' => $order->id,
                            'operation_id' => $currentOrderOp->id,
                            'predecessor_operation_id' => $predOrderOp->id,
                            'dependency_type' => 'FS',
                        ]);
                    }
                } else {
                    $currentOrderOp->previous_operation_id = $sortedOps[$i - 1]->id;
                }
                $currentOrderOp->save();
            }
        }

        // Auto-orchestrate Subcontract Procurement according to tenant settings (Manual PR/PO, Auto Draft PO, Auto Approved PO)
        foreach ($createdOps as $createdOp) {
            if ($createdOp->is_external) {
                try {
                    app(SubcontractProcurementOrchestrator::class)->orchestrateSubcontractProcurement($createdOp, $tenantId, $userId);
                } catch (\Throwable $e) {
                    // Procurement orchestration fallback
                }
            }
        }

        // 2. Recursively inspect component BOM items for child SFGs
        $bomBaseQty = $bom->base_quantity > 0 ? (float) $bom->base_quantity : 1.0;
        $multiplier = $targetQty / $bomBaseQty;

        foreach ($this->primaryBomRows($bom) as $item) {
            $childProductId = $item->material_id;
            $totalRequired = $this->resolveBomRowGrossQuantity($item) * $multiplier;

            // PO-IMPL-04 (PO-04): branch available = SUM(max(0, quantity - reserved_qty)) over the branch's standard warehouses
            $warehouseAvailable = (float) (app(ProductionOrderEngineeringService::class)
                ->getBranchAvailableStockMap($tenantId, $order->branch_id, [$childProductId])[$childProductId] ?? 0.0);

            $netManufacturingQty = max(0.0, $totalRequired - $warehouseAvailable);

            // Find child BOM and Routing if component is manufactured
            $childBom = null;
            if ($item->child_bom_id) {
                $childBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $item->child_bom_id)
                    ->first();
            } else {
                $childBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $childProductId)
                    ->whereIn('status', ['approved', 'active'])
                    ->whereIn('bom_type', ['manufacturing', 'subcontracting'])
                    ->first();
            }

            if ($childBom && $netManufacturingQty > 0) {
                // PO-IMPL-04: the child BOM's own routing (routings.product_id no longer exists)
                $childRouting = $childBom->routing_id
                    ? Routing::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($childBom->routing_id)
                    : null;
                if ($childRouting && !$childRouting->isCurrentlyActive()) {
                    $childRouting = null;
                }

                if ($childRouting && $childRouting->operations->isNotEmpty()) {
                    // Recursively snapshot child SFG routing
                    $childOps = $this->buildMultiLevelSnapshot(
                        $order,
                        $childProductId,
                        $childBom,
                        $childRouting,
                        $netManufacturingQty,
                        $level + 1,
                        true, // is_intermediate
                        $tenantId,
                        $visited,
                        $createdComponentOps,
                        $userId
                    );

                    // Clarification Rule 1: Find the actual consuming parent operation
                    $sfgFinalOp = end($childOps);

                    $consumingParentOp = null;
                    // Check if any operation in parent routing has explicit RoutingOperationMaterial matching childProductId
                    foreach ($createdOps as $parentOp) {
                        if ($parentOp->routing_operation_id) {
                            $hasMaterial = RoutingOperationMaterial::where('routing_operation_id', $parentOp->routing_operation_id)
                                ->where('material_id', $childProductId)
                                ->exists();
                            if ($hasMaterial) {
                                $consumingParentOp = $parentOp;
                                break;
                            }
                        }
                    }
                    // Fallback to first parent operation if no explicit mapping
                    if (!$consumingParentOp) {
                        $consumingParentOp = $createdOps[0] ?? null;
                    }

                    if ($sfgFinalOp && $consumingParentOp) {
                        ProductionOrderOperationDependency::firstOrCreate(
                            [
                                'operation_id' => $consumingParentOp->id,
                                'predecessor_operation_id' => $sfgFinalOp->id,
                            ],
                            [
                                'tenant_id' => $tenantId,
                                'production_order_id' => $order->id,
                                'dependency_type' => 'cross_assembly',
                            ]
                        );
                    }
                }
            }
        }

        $createdComponentOps[$productId] = $createdOps;
        unset($visited[$productId]);
        return $createdOps;
    }

    private function createOrderReservationsForBom(
        ProductionOrder $order,
        ProductionBom $bom,
        float $targetQty,
        int $tenantId,
        array $parameters = [],
        array &$visitedBoms = []
    ): void {
        if (in_array($bom->id, $visitedBoms, true)) {
            return;
        }
        $visitedBoms[] = $bom->id;

        $baseQty = $bom->base_quantity > 0 ? (float) $bom->base_quantity : 1.0;
        $multiplier = $targetQty / $baseQty;

        foreach ($this->primaryBomRows($bom) as $item) {
            if (!$item->material_id) continue;

            $grossUnitQty = $this->resolveBomRowGrossQuantity($item);
            $plannedQty = max(0.0, $grossUnitQty * $multiplier);

            // PO-IMPL-04 (PO-12): one reservation per (order, product); shared materials are summed, never skipped
            $this->accumulateReservation($order, $item->material_id, $plannedQty, $item->uom_id ?? ($item->material?->uom_id ?? 1), $this->resolveBomRowBomItemId($item));

            // Recurse for child SFG BOMs
            $childBom = !empty($item->child_bom_id)
                ? ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($item->child_bom_id)
                : ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->material_id)
                    ->whereIn('status', ['approved', 'active'])
                    ->whereIn('bom_type', ['manufacturing', 'subcontracting'])
                    ->first();

            if ($childBom) {
                $this->createOrderReservationsForBom($order, $childBom, $plannedQty, $tenantId, $parameters, $visitedBoms);
            }
        }
    }

    /**
     * PO-IMPL-04 (PO-12): one reservation per (order, product) — quantities of shared materials are added up.
     */
    private function accumulateReservation(ProductionOrder $order, int $productId, float $plannedQty, ?int $uomId, ?int $bomItemId = null): ProductionOrderReservation
    {
        $existing = ProductionOrderReservation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->quantity_planned = round((float) $existing->quantity_planned + $plannedQty, 4);
            $existing->save();

            return $existing;
        }

        return ProductionOrderReservation::create([
            'tenant_id' => $order->tenant_id,
            'production_order_id' => $order->id,
            'bom_item_id' => $bomItemId,
            'product_id' => $productId,
            'warehouse_id' => $this->resolveReservationWarehouseId($order->tenant_id, $productId, $order->branch_id),
            'quantity_planned' => round($plannedQty, 4),
            'quantity_reserved' => 0.0,
            'quantity_issued' => 0.0,
            'uom_id' => $uomId ?: (Product::withoutGlobalScopes()->where('id', $productId)->value('uom_id') ?? 1),
        ]);
    }

    /**
     * PO-IMPL-04 (PO-12): reservations + requisition slip lines straight from the multi-level plan
     * (already consolidated per product, matching Create screen Tab 2).
     */
    private function persistPlannedMaterials(ProductionOrder $order, array $materials): void
    {
        // UR-11 (M-23 / M-24): the material PLAN (reservation rows) is kept, but nothing is requested from the store here any more —
        // the user selects the items and sends the request(s) by hand (sendMaterialRequest()).
        foreach ($materials as $m) {
            $productId = (int) $m['product_id'];
            $qty = (float) $m['quantity_planned'];
            if ($qty <= 0) {
                continue;
            }
            $this->accumulateReservation($order, $productId, $qty, $m['uom_id'] ?? null, $m['bom_item_id'] ?? null);
        }
    }

    /**
     * UR-12 (M-24): true when the order has planned material and none of it has been issued to production yet.
     * Releasing / scheduling is still allowed then — only after the user confirmed it.
     */
    public function materialsNotReleased(ProductionOrder $order): bool
    {
        $reservations = ProductionOrderReservation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->get();

        $planned = (float) $reservations->sum('quantity_planned');
        if ($planned <= 0) {
            return false;
        }

        return (float) $reservations->sum('quantity_issued') <= 0;
    }

    /**
     * UR-11 — the planned materials of an order with what has been requested from the store so far (slips that are not cancelled,
     * "ad-hoc" extra requests excluded: they are on top of the plan) and what is still left to request.
     *
     * @return \Illuminate\Support\Collection<int, array{product_id: int, reservation: ProductionOrderReservation, planned: float, requested: float, remaining: float}>
     */
    public function materialRequestLines(ProductionOrder $order): \Illuminate\Support\Collection
    {
        $reservations = ProductionOrderReservation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->where('quantity_planned', '>', 0)
            ->with('product')
            ->orderBy('id')
            ->get();

        $requested = ProductionRequisitionSlipItem::where('tenant_id', $order->tenant_id)
            ->whereHas('slip', function ($q) use ($order) {
                $q->where('production_order_id', $order->id)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->where(fn ($n) => $n->whereNull('notes')->orWhere('notes', 'not like', 'Ad-hoc%'));
            })
            ->get()
            ->groupBy('product_id')
            ->map(fn ($items) => round((float) $items->sum('quantity_planned'), 4));

        return $reservations->map(function (ProductionOrderReservation $r) use ($requested) {
            $planned = round((float) $r->quantity_planned, 4);
            $done = (float) ($requested[$r->product_id] ?? 0.0);

            return [
                'product_id' => (int) $r->product_id,
                'reservation' => $r,
                'planned' => $planned,
                'requested' => $done,
                'remaining' => max(0.0, round($planned - $done, 4)),
            ];
        });
    }

    /**
     * UR-11 (M-23 / M-24) — send ONE request to the store with the items the user selected. An order can have many requests; every request is
     * its own requisition slip. The first request locks a draft order (UR-12): from then on it cannot be edited or deleted, only cancelled or released.
     *
     * @param array<int, array{product_id: int|string, quantity: float|int|string}> $items
     */
    public function sendMaterialRequest(ProductionOrder $order, array $items, ?int $userId = null, ?string $notes = null): ProductionRequisitionSlip
    {
        if ($order->isCompleted() || $order->isClosed() || $order->isCancelled()) {
            throw new InvalidArgumentException('Cannot request material for a completed, closed or cancelled order.');
        }

        return DB::transaction(function () use ($order, $items, $userId, $notes) {
            $lines = $this->materialRequestLines($order)->keyBy('product_id');

            $wanted = [];
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $qty = round((float) ($item['quantity'] ?? 0), 4);
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }
                $line = $lines->get($productId);
                if (!$line) {
                    throw new InvalidArgumentException("Product #{$productId} is not a planned material of this order.");
                }
                $wanted[$productId] = ($wanted[$productId] ?? 0.0) + $qty;
                if ($wanted[$productId] > $line['remaining'] + 0.0001) {
                    $name = $line['reservation']->product?->name ?? ('#' . $productId);
                    throw new InvalidArgumentException("{$name}: only " . rtrim(rtrim(number_format($line['remaining'], 4, '.', ''), '0'), '.') . ' is left to request.');
                }
            }
            if ($wanted === []) {
                throw new InvalidArgumentException('Select at least one material to request.');
            }

            $slip = $this->newRequisitionSlip(
                $order,
                'Material request for Production Order ' . $order->order_number . ($notes ? ' — ' . $notes : ''),
                $userId ?? auth()->id()
            );
            foreach ($wanted as $productId => $qty) {
                $reservation = $lines->get($productId)['reservation'];
                $this->createRequisitionSlipItem($slip, (int) $productId, $qty, $reservation->uom_id, $reservation->warehouse_id);
            }

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'material_requested',
                'title' => 'Material Request Sent to Store',
                'description' => "Material request {$slip->requisition_number} sent to the store (" . count($wanted) . ' items).',
                'severity' => 'info',
                'event_source' => 'ProductionOrderService',
                'triggered_by' => $userId ?? auth()->id(),
            ]);

            $this->lockOnFirstRequest($order, $userId);

            return $slip;
        });
    }

    /**
     * UR-12 (M-24): the first material request turns a draft order into a LOCKED order.
     */
    private function lockOnFirstRequest(ProductionOrder $order, ?int $userId = null): void
    {
        $order->refresh();
        if ($order->status !== ProductionOrder::STATUS_DRAFT) {
            return;
        }

        $order->status = ProductionOrder::STATUS_LOCKED;
        $order->save();

        app(ProductionEventService::class)->writeEvent($order->tenant_id, [
            'production_order_id' => $order->id,
            'event_type' => 'Order Locked',
            'title' => 'Production Order Locked',
            'description' => "Production order {$order->order_number} is locked: the first material request was sent. It can no longer be edited or deleted — only cancelled or released.",
            'severity' => 'info',
            'event_source' => 'ProductionOrderService',
            'triggered_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * PO-IMPL-04 (PO-12): create order operations for the planned tree — chosen parent operations (level 1)
     * and the primary operations of exploded child BOMs (level 2+), with dependencies inside each BOM's
     * routing and a cross-assembly link from each child BOM's last operation to the parent operation using it.
     */
    /**
     * Temp no-BOM flow: snapshot the routing's primary operations (every resource / time field optional) onto the order.
     * Without a routing a single default "Production" operation is created so WIP, scheduling and logging still work.
     */
    private function snapshotTempFlowOperations(ProductionOrder $order, ?Routing $routing, ?int $userId = null): void
    {
        $quantity = (float) $order->quantity_ordered;
        $routingOps = $routing
            ? $routing->operations()->where('is_alternative', false)->orderBy('sequence')->get()
            : collect();

        if ($routingOps->isEmpty()) {
            ProductionOrderOperation::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'source_product_id' => $order->product_id,
                'bom_level' => 1,
                'target_produced_qty' => round($quantity, 4),
                'is_intermediate' => false,
                'quantity_claimed' => 0.0000,
                'sequence' => 10,
                'operation_number' => 'OP-010',
                'name' => 'Production',
                'status' => ProductionOrderOperation::STATUS_WAITING,
                'setup_time_planned' => 0,
                'processing_time_planned' => 0,
                'total_time_planned' => 0,
                'setup_time_actual' => 0.00,
                'processing_time_actual' => 0.00,
                'quantity_produced' => 0.0000,
                'quantity_rejected' => 0.0000,
                'quantity_scrapped' => 0.0000,
                'is_external' => false,
            ]);
            $this->reconcileOperationReadiness($order);

            return;
        }

        $entries = $routingOps->map(fn ($op) => [
            'routing_operation' => $op,
            'target_produced_qty' => $quantity,
            'selected_routing_material_ids' => [],
            'source_product_id' => $order->product_id,
            'source_bom_id' => null,
            'source_routing_id' => $routing->id,
            'bom_level' => 1,
            'is_intermediate' => false,
        ])->all();

        $this->snapshotPlannedOperations($order, $entries, $userId);
    }

    /**
     * The columns of an order operation that are copied from a plan entry (routing operation snapshot).
     */
    private function plannedOperationAttributes(ProductionOrder $order, array $entry): array
    {
        $routingOp = $entry['routing_operation'];
        $target = (float) $entry['target_produced_qty'];
        $processingTime = (float) $routingOp->processing_time_minutes * $target;

        return [
            'tenant_id' => $order->tenant_id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $routingOp->id,
            'selected_routing_material_ids' => $entry['selected_routing_material_ids'],
            'source_product_id' => $entry['source_product_id'],
            'source_bom_id' => $entry['source_bom_id'],
            'source_routing_id' => $entry['source_routing_id'],
            'bom_level' => $entry['bom_level'],
            'target_produced_qty' => round($target, 4),
            'is_intermediate' => (bool) $entry['is_intermediate'],
            'quantity_claimed' => 0.0000,
            'sequence' => $routingOp->sequence,
            'operation_number' => $routingOp->operation_number ?: ('OP-' . str_pad((string) $routingOp->sequence, 3, '0', STR_PAD_LEFT)),
            'name' => $routingOp->name,
            'work_center_id' => $routingOp->work_center_id,
            'section_id' => $routingOp->section_id,
            'machine_id' => $routingOp->machine_id,
            'section_manual_production_id' => $routingOp->section_manual_production_id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => (float) ($routingOp->setup_time_minutes ?? 0),
            'processing_time_planned' => $processingTime,
            'total_time_planned' => (float) ($routingOp->setup_time_minutes ?? 0) + $processingTime,
            'setup_time_actual' => 0.00,
            'processing_time_actual' => 0.00,
            'quantity_produced' => 0.0000,
            'quantity_rejected' => 0.0000,
            'quantity_scrapped' => 0.0000,
            'is_external' => (bool) ($routingOp->is_external ?? false),
            'quality_required' => (bool) ($routingOp->quality_required ?? false),
            'vendor_id' => $routingOp->vendor_id,
            'subcontract_lead_time_days' => (int) ($routingOp->subcontract_lead_time_days ?? 0),
            'subcontract_cost_per_unit' => (float) ($routingOp->subcontract_cost_per_unit ?? 0.0),
            'subcontract_service_product_id' => $routingOp->subcontract_service_product_id,
            'material_supply_type' => $routingOp->material_supply_type ?? (in_array($order->production_model, ['complete_subcontracting', 'subcontract_complete']) ? 'vendor_supplied' : 'company_supplied'),
            'subcontract_input_type' => $routingOp->subcontract_input_type ?? 'bom_raw_materials',
            'dispatch_buffer_days' => (int) ($routingOp->dispatch_buffer_days ?? 0),
            'return_buffer_days' => (int) ($routingOp->return_buffer_days ?? 0),
            'queue_threshold_enabled' => (bool) ($routingOp->queue_threshold_enabled ?? $routingOp->overlap_enabled ?? false),
            'overlap_enabled' => (bool) ($routingOp->queue_threshold_enabled ?? $routingOp->overlap_enabled ?? false),
            'transfer_batch_quantity' => (float) ($routingOp->transfer_batch_quantity ?? 0.0000),
            'transfer_lag_minutes' => (int) ($routingOp->transfer_lag_minutes ?? 0),
            'is_parallel' => (bool) ($routingOp->is_parallel ?? false),
            'parallel_group' => $routingOp->parallel_group,
            'parallel_type' => $routingOp->parallel_type ?? 'AND',
        ];
    }

    /**
     * UR-10 — (re)build the operations of a plan made by ProductionOrderEngineeringService::computeMultiLevelPlan().
     * Every operation carries its plan key and execution order. Operations that already exist (same plan key) are updated in place
     * (so what the user set on them stays), new ones are created, the ones the plan no longer has are removed, and the links
     * "sub-assembly operation → the operation that needs it" are rebuilt: each set of makers is a predecessor of the operations it serves.
     *
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, mixed>|null $planMeta  stored in production_orders.sub_assembly_plan (stock, demands, merges)
     * @return array<string, ProductionOrderOperation>  plan key => operation
     */
    public function syncPlannedTree(ProductionOrder $order, array $entries, ?int $userId = null, ?array $planMeta = null, bool $isResync = false): array
    {
        usort($entries, fn ($a, $b) => ProductionOrderEngineeringService::comparePlanPaths($a['exec_path'], $b['exec_path']));

        $existing = ProductionOrderOperation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->whereNotNull('plan_key')
            ->get()
            ->keyBy('plan_key');

        $byKey = [];
        $createdNow = [];
        foreach ($entries as $i => $entry) {
            $attributes = $this->plannedOperationAttributes($order, $entry);
            $attributes['plan_key'] = $entry['op_key'];
            $attributes['execution_order'] = $i + 1;

            $op = $existing->get($entry['op_key']);
            if ($op) {
                $op->update([
                    'target_produced_qty' => $attributes['target_produced_qty'],
                    'processing_time_planned' => $attributes['processing_time_planned'],
                    'total_time_planned' => $attributes['total_time_planned'],
                    'bom_level' => $attributes['bom_level'],
                    'execution_order' => $attributes['execution_order'],
                ]);
            } else {
                $op = ProductionOrderOperation::create($attributes);
                $createdNow[] = $op;
            }
            $byKey[$entry['op_key']] = $op;
        }

        // operations the plan no longer has (a set that was merged into an earlier one)
        $obsolete = $existing->reject(fn ($op, $key) => isset($byKey[$key]));
        if ($obsolete->isNotEmpty()) {
            $obsoleteIds = $obsolete->pluck('id')->all();
            ProductionOrderOperationDependency::where('production_order_id', $order->id)
                ->where(fn ($q) => $q->whereIn('operation_id', $obsoleteIds)->orWhereIn('predecessor_operation_id', $obsoleteIds))
                ->delete();
            ProductionOrderOperation::where('production_order_id', $order->id)->whereIn('id', $obsoleteIds)->delete();
        }

        // the chain inside each set (and inside the order's own routing)
        $bySet = [];
        $setEntries = [];
        foreach ($entries as $entry) {
            $groupKey = $entry['set_key'] ?? 'root';
            $bySet[$groupKey][] = $byKey[$entry['op_key']];
            $setEntries[$groupKey] ??= $entry;
        }
        foreach ($bySet as $setOps) {
            $sorted = collect($setOps)->sortBy('sequence')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $current = $sorted[$i];
                $rOp = $current->routingOperation;
                if ($rOp && $rOp->is_parallel) {
                    $current->previous_operation_id = null;
                } else {
                    $pred = ($rOp && $rOp->previous_operation_id) ? $sorted->firstWhere('routing_operation_id', $rOp->previous_operation_id) : null;
                    $pred ??= $sorted[$i - 1];
                    $current->previous_operation_id = $pred->id;
                    if ($rOp && $rOp->previous_operation_id && (int) $pred->routing_operation_id === (int) $rOp->previous_operation_id) {
                        ProductionOrderOperationDependency::firstOrCreate([
                            'tenant_id' => $order->tenant_id,
                            'production_order_id' => $order->id,
                            'operation_id' => $current->id,
                            'predecessor_operation_id' => $pred->id,
                            'dependency_type' => 'FS',
                        ]);
                    }
                }
                if ($current->isDirty()) {
                    $current->save();
                }
            }
        }

        // links: every operation of a set must be finished before each operation the set serves (the last one is the "cross_assembly" link,
        // the others are "sub_assembly" = ordering only). Rebuilt from scratch — they are fully described by the plan.
        ProductionOrderOperationDependency::where('production_order_id', $order->id)
            ->whereIn('dependency_type', ['cross_assembly', 'sub_assembly'])
            ->delete();
        foreach ($bySet as $groupKey => $setOps) {
            if ($groupKey === 'root') {
                continue;
            }
            $sorted = collect($setOps)->sortBy('sequence')->values();
            $final = $sorted->last();
            foreach ((array) ($setEntries[$groupKey]['serves_keys'] ?? []) as $consumerKey) {
                $consumer = $byKey[$consumerKey] ?? null;
                if (!$consumer) {
                    continue;
                }
                foreach ($sorted as $maker) {
                    ProductionOrderOperationDependency::firstOrCreate(
                        ['operation_id' => $consumer->id, 'predecessor_operation_id' => $maker->id],
                        ['tenant_id' => $order->tenant_id, 'production_order_id' => $order->id, 'dependency_type' => (int) $maker->id === (int) $final->id ? 'cross_assembly' : 'sub_assembly']
                    );
                }
            }
        }

        if ($planMeta !== null) {
            $order->sub_assembly_plan = array_merge((array) $order->sub_assembly_plan, $planMeta);
            $order->save();
        }

        foreach ($createdNow as $createdOp) {
            if ($createdOp->is_external) {
                try {
                    app(SubcontractProcurementOrchestrator::class)->orchestrateSubcontractProcurement($createdOp, $order->tenant_id, $userId);
                } catch (\Throwable $e) {
                    // Procurement orchestration fallback
                }
            }
        }

        $this->reconcileOperationReadiness($order);

        if (!$isResync) {
            $currentModel = $order->production_model;
            if (empty($currentModel) || $currentModel === ProductionOrder::MODEL_PURE_MANUFACTURING) {
                $fallback = $order->product?->default_production_model ?? ProductionOrder::MODEL_PURE_MANUFACTURING;
                $order->production_model = ProductionOrder::determineProductionModelFromOperations($order->operations()->get(), $fallback);
                $order->save();
            }
        }

        return $byKey;
    }

    private function snapshotPlannedOperations(ProductionOrder $order, array $entries, ?int $userId = null, ?array $planMeta = null): void
    {
        // UR-10: plans from the multi-level engine carry plan keys — one set of sub-assembly operations per operation that needs it
        if ($entries !== [] && isset($entries[array_key_first($entries)]['op_key'])) {
            $this->syncPlannedTree($order, $entries, $userId, $planMeta ?? ['stock' => [], 'demands' => [], 'merges' => []]);

            return;
        }

        $created = [];
        foreach ($entries as $entry) {
            $created[] = ProductionOrderOperation::create($this->plannedOperationAttributes($order, $entry));
        }

        // Dependencies inside each BOM's routing
        $byBom = collect($created)->groupBy(fn ($op) => $op->source_bom_id . ':' . $op->bom_level);
        foreach ($byBom as $branchOps) {
            $sorted = $branchOps->sortBy('sequence')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $current = $sorted[$i];
                $rOp = $current->routingOperation;
                if ($rOp && $rOp->is_parallel) {
                    $current->previous_operation_id = null;
                } else {
                    // A chosen alternative replaces its primary — link to the snapshotted predecessor, else the previous one
                    $pred = ($rOp && $rOp->previous_operation_id) ? $sorted->firstWhere('routing_operation_id', $rOp->previous_operation_id) : null;
                    $pred ??= $sorted[$i - 1];
                    $current->previous_operation_id = $pred->id;
                    if ($rOp && $rOp->previous_operation_id && (int) $pred->routing_operation_id === (int) $rOp->previous_operation_id) {
                        ProductionOrderOperationDependency::firstOrCreate([
                            'tenant_id' => $order->tenant_id,
                            'production_order_id' => $order->id,
                            'operation_id' => $current->id,
                            'predecessor_operation_id' => $pred->id,
                            'dependency_type' => 'FS',
                        ]);
                    }
                }
                $current->save();
            }
        }

        // Cross-assembly: child BOM's last operation → EVERY operation (of a shallower level) that consumes the child product.
        // (A sub-assembly can be needed at two levels — e.g. by the order's own BOM and by another child BOM — so there can be several consumers.)
        foreach ($byBom as $branchOps) {
            $first = $branchOps->first();
            if ((int) $first->bom_level < 2) {
                continue;
            }
            $final = $branchOps->sortBy('sequence')->last();
            $shallower = collect($created)->filter(fn ($op) => (int) $op->bom_level < (int) $first->bom_level);
            $consumers = $shallower->filter(function ($op) use ($first) {
                $ids = is_array($op->selected_routing_material_ids) ? $op->selected_routing_material_ids : [];

                return $ids !== [] && RoutingOperationMaterial::withoutGlobalScopes()
                    ->whereIn('id', $ids)
                    ->where('material_id', $first->source_product_id)
                    ->where('child_bom_id', $first->source_bom_id)
                    ->exists();
            });
            if ($consumers->isEmpty()) {
                // legacy rows without a child-BOM link: the first operation one level up
                $fallback = $shallower->filter(fn ($op) => (int) $op->bom_level === (int) $first->bom_level - 1)->sortBy('sequence')->first();
                $consumers = $fallback ? collect([$fallback]) : collect();
            }

            if ($final) {
                foreach ($consumers as $consumer) {
                    ProductionOrderOperationDependency::firstOrCreate(
                        ['operation_id' => $consumer->id, 'predecessor_operation_id' => $final->id],
                        ['tenant_id' => $order->tenant_id, 'production_order_id' => $order->id, 'dependency_type' => 'cross_assembly']
                    );
                }
            }
        }

        // The parent waits for ALL the operations that make its sub-assembly, not only the last one (type sub_assembly = ordering only)
        app(SubAssemblyDependencyService::class)->ensure($order);

        foreach ($created as $createdOp) {
            if ($createdOp->is_external) {
                try {
                    app(SubcontractProcurementOrchestrator::class)->orchestrateSubcontractProcurement($createdOp, $order->tenant_id, $userId);
                } catch (\Throwable $e) {
                    // Procurement orchestration fallback
                }
            }
        }

        $this->reconcileOperationReadiness($order);

        $currentModel = $order->production_model;
        if (empty($currentModel) || $currentModel === ProductionOrder::MODEL_PURE_MANUFACTURING) {
            $fallback = $order->product?->default_production_model ?? ProductionOrder::MODEL_PURE_MANUFACTURING;
            $order->production_model = ProductionOrder::determineProductionModelFromOperations($order->operations()->get(), $fallback);
            $order->save();
        }
    }

    /**
     * Recursively calculate component requirement ratio across multi-level BOM tree.
     */
    public function calculateBomComponentRatio(?ProductionBom $bom, int $materialId, int $depth = 0): ?float
    {
        if (!$bom || $depth > 5) {
            return null;
        }

        $baseQty = $bom->base_quantity > 0 ? (float) $bom->base_quantity : 1.0;
        $rows = $this->primaryBomRows($bom);
        $directMatches = $rows->where('material_id', $materialId);
        if ($directMatches->isNotEmpty()) {
            $directQty = (float) $directMatches->sum('quantity');
            if ($directQty > 0) {
                return $directQty / $baseQty;
            }
        }

        // Recursively search child BOMs
        foreach ($rows as $item) {
            if ((float) $item->quantity <= 0) {
                continue;
            }
            $childBom = !empty($item->child_bom_id)
                ? ProductionBom::withoutGlobalScopes()->where('tenant_id', $bom->tenant_id)->find($item->child_bom_id)
                : ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $bom->tenant_id)
                    ->where('product_id', $item->material_id)
                    ->whereIn('status', ['approved', 'active'])
                    ->first();

            if ($childBom) {
                $childRatio = $this->calculateBomComponentRatio($childBom, $materialId, $depth + 1);
                if ($childRatio !== null && $childRatio > 0) {
                    $parentRatio = (float) $item->quantity / $baseQty;
                    return $parentRatio * $childRatio;
                }
            }
        }

        return null;
    }

    /**
     * Primary component rows from operation allocations, falling back to legacy BOM items when unallocated.
     */
    private function primaryBomRows(ProductionBom $bom): \Illuminate\Support\Collection
    {
        $bom->loadMissing(['operationMaterials.material', 'items.material']);
        $rows = $bom->operationMaterials->isNotEmpty()
            ? $bom->operationMaterials
            : $bom->items;

        return $rows->where('is_alternative', false)->values();
    }

    private function resolveBomRowBomItemId($row): ?int
    {
        return $row instanceof \App\Domains\Production\Models\RoutingOperationMaterial
            ? ($row->bom_item_id ?: null)
            : ($row->id ?? null);
    }

    private function resolveBomRowGrossQuantity($row): float
    {
        if (method_exists($row, 'calculateGrossQuantity')) {
            return (float) ($row->gross_quantity ?? $row->calculateGrossQuantity());
        }

        $qty = (float) ($row->quantity ?? 0);
        $scrapPct = (float) ($row->material_scrap_percentage ?? 0);
        return round($qty * (1 + ($scrapPct / 100.0)), 4);
    }

    /**
     * PX-09 (decision D-04 A): operations that would be BLOCKED right now (material not issued / short, machine down, quality).
     * Releasing is still allowed, but only after the user confirms these. Workflow waits (predecessor not done) are not listed.
     *
     * @return array<int, string> "Op #10: message"
     */
    public function releaseWarnings(ProductionOrder $order): array
    {
        $readiness = app(ProductionReadinessService::class)->evaluateOrderReadiness($order->fresh());

        return collect($readiness['actionable_blockers'] ?? [])
            ->map(fn ($b) => 'Op #' . ($b['sequence'] ?? '—') . ': ' . ($b['message'] ?? 'Blocker'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * PX-18: order.quantity_produced = cumulative good output of the final FG operation (never lowered; FG receipts use max() too).
     */
    public function syncProducedQuantity(ProductionOrder $order): void
    {
        $fgOps = $order->operations()
            ->where('is_intermediate', false)
            ->where(fn ($q) => $q->whereNull('source_product_id')->orWhere('source_product_id', $order->product_id))
            ->orderBy('sequence')
            ->get();

        $final = $fgOps->last();
        if (!$final) {
            return;
        }

        $good = (float) $final->quantity_produced;
        if ($good > (float) $order->quantity_produced) {
            $order->quantity_produced = $good;
            $order->save();
        }
    }

    /**
     * Reconcile initial readiness status for all operations of an order.
     * An operation is READY if it has no uncompleted predecessor operations.
     * An operation is WAITING if any of its predecessors are incomplete.
     */
    public function reconcileOperationReadiness(ProductionOrder|int $orderOrId): void
    {
        $order = is_numeric($orderOrId) ? ProductionOrder::find((int) $orderOrId) : $orderOrId;
        if (!$order) {
            return;
        }

        // PX-10: a draft (or locked) order carries no readiness-driven operation status (it is computed from release on).
        if ($order->isPreRelease()) {
            return;
        }

        // UR-05: when the order screen already holds the operations, refresh THOSE instances so the screen shows the saved status it just refreshed.
        $ops = $order->relationLoaded('operations')
            ? $order->operations->loadMissing(['predecessorDependencies', 'scheduleOperation'])
            : $order->operations()->with(['predecessorDependencies', 'scheduleOperation'])->get();
        if ($ops->isEmpty()) {
            return;
        }

        $readinessService = app(ProductionReadinessService::class);
        $readinessService->beginOrderContext($order);   // every operation resolved once for the whole loop

        try {
        foreach ($ops as $op) {
            // Do not override terminal or active progress states
            if (in_array($op->status, [
                ProductionOrderOperation::STATUS_COMPLETED,
                ProductionOrderOperation::STATUS_SKIPPED,
                ProductionOrderOperation::STATUS_CANCELLED,
                ProductionOrderOperation::STATUS_RUNNING,
                ProductionOrderOperation::STATUS_PAUSED,
                'subcontract_qc_pending',
            ], true)) {
                continue;
            }

            $eval = $readinessService->evaluateOperationReadiness($op);

            // An operation with quality_required on its own output cannot be blocked from
            // STARTING merely because its post-production quality inspection has not yet occurred.
            // Only genuine execution blockers (materials, machines, predecessor WIP, open NCRs, or failed QC) prevent starting.
            $blockingReasons = collect($eval['blocking_reasons'] ?? []);
            $hardExecutionBlockers = $blockingReasons->reject(function ($b) use ($op) {
                return ($b['code'] ?? '') === ProductionReadinessService::REASON_QC_PENDING
                    && (float) $op->quantity_produced <= 0
                    && (float) ($op->active_qc_hold ?? 0) <= 0;
            });

            // UR-02: a predecessor wait is reported as WAITING_PREDECESSOR (no longer BLOCKED) — either way the operation is not ready yet.
            $dependencyWaits = in_array($eval['dependency']['status'] ?? '', [
                ProductionReadinessService::STATUS_BLOCKED,
                ProductionReadinessService::STATUS_WAITING_PREDECESSOR,
            ], true);
            $newStatus = ($hardExecutionBlockers->isEmpty() && !$dependencyWaits)
                ? ProductionOrderOperation::STATUS_READY
                : ProductionOrderOperation::STATUS_WAITING;

            if ($op->status !== $newStatus) {
                $op->status = $newStatus;
                $op->save();
            }

            if ($op->scheduleOperation && !in_array($op->scheduleOperation->status, [
                ProductionScheduleOperation::STATUS_COMPLETED,
                ProductionScheduleOperation::STATUS_SKIPPED,
                ProductionScheduleOperation::STATUS_CANCELLED,
                ProductionScheduleOperation::STATUS_RUNNING,
                ProductionScheduleOperation::STATUS_PAUSED,
            ], true)) {
                $schedStatus = ($newStatus === ProductionOrderOperation::STATUS_READY)
                    ? ProductionScheduleOperation::STATUS_READY
                    : ProductionScheduleOperation::STATUS_WAITING;

                if ($op->scheduleOperation->status !== $schedStatus) {
                    $op->scheduleOperation->status = $schedStatus;
                    $op->scheduleOperation->save();
                }
            }
        }
        } finally {
            $readinessService->endOrderContext($order);
        }
    }
}
