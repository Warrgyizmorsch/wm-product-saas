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
     * Convert an approved Production Plan into a Production Order with frozen snapshots.
     */
    public function createFromPlan(int $planId, ?int $userId = null): ProductionOrder
    {
        return DB::transaction(function () use ($planId, $userId) {
            $plan = ProductionPlan::with(['requirements', 'operations'])->findOrFail($planId);

            if ($plan->status !== ProductionPlan::STATUS_APPROVED && $plan->status !== ProductionPlan::STATUS_MRP_GENERATED) {
                throw new InvalidArgumentException('Only approved or MRP-generated production plans can be converted to production orders.');
            }

            $product = Product::find($plan->product_id);
            $productionMode = !empty($plan->production_mode)
                ? $plan->production_mode
                : ($product ? $product->getDefaultProductionMode() : 'standard');
            $productionModel = !empty($plan->production_model)
                ? $plan->production_model
                : ($product->default_production_model ?? ProductionOrder::MODEL_PURE_MANUFACTURING);

            // 1. Create order header
            $order = ProductionOrder::create([
                'tenant_id' => $plan->tenant_id,
                'order_number' => $this->numberService->generateNextNumber($plan->tenant_id),
                'production_plan_id' => $plan->id,
                'product_id' => $plan->product_id,
                'bom_id' => $plan->bom_id,
                'routing_id' => $plan->routing_id,
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

            // 2. Clone Planning Requirements -> Order Reservations
            $itemsToResolve = [];
            foreach ($plan->requirements as $req) {
                $this->createMaterialReservation(
                    $order,
                    $req->bom_item_id,
                    $req->product_id,
                    (float) $req->required_quantity,
                    $req->uom_id
                );

                $itemsToResolve[] = [
                    'product_id' => $req->product_id,
                    'planned_qty' => (float) $req->required_quantity,
                    'uom_id' => $req->uom_id,
                    'child_bom_id' => null,
                ];
            }
            $this->createRequisitionSlip($order, $itemsToResolve);

            // 3. Clone Planning Operations -> Order Operations (snapshot)
            $createdOps = [];
            foreach ($plan->operations as $idx => $planOp) {
                $status = ($idx === 0) ? ProductionOrderOperation::STATUS_READY : ProductionOrderOperation::STATUS_WAITING;

                $routingOp = $planOp->routingOperation;
                $op = ProductionOrderOperation::create([
                    'tenant_id' => $order->tenant_id,
                    'production_order_id' => $order->id,
                    'routing_operation_id' => $planOp->routing_operation_id,
                    'sequence' => $planOp->sequence,
                    'operation_number' => $planOp->operation_number,
                    'name' => $planOp->name,
                    'work_center_id' => $planOp->work_center_id,
                    'machine_id' => $planOp->machine_id,
                    'status' => $status,
                    'setup_time_planned' => $planOp->setup_time_minutes,
                    'processing_time_planned' => $planOp->processing_time_minutes,
                    'total_time_planned' => $planOp->total_time_minutes,
                    'setup_time_actual' => 0.00,
                    'processing_time_actual' => 0.00,
                    'quantity_produced' => 0.0000,
                    'quantity_rejected' => 0.0000,
                    'quantity_scrapped' => 0.0000,
                    'is_external' => (bool) (($planOp->is_external ?? $routingOp?->is_external ?? false) || in_array($order->production_model, ['complete_subcontracting', 'subcontract_company_material'])),
                    'subcontract_lead_time_days' => (int) ($routingOp?->subcontract_lead_time_days ?? 0),
                    'subcontract_cost_per_unit' => (float) ($routingOp?->subcontract_cost_per_unit ?? 0.0),
                    'subcontract_service_product_id' => $routingOp?->subcontract_service_product_id,
                    'material_supply_type' => $routingOp?->material_supply_type ?? ($order->production_model === 'complete_subcontracting' ? 'vendor_supplied' : 'company_supplied'),
                    'dispatch_buffer_days' => (int) ($routingOp?->dispatch_buffer_days ?? 0),
                    'return_buffer_days' => (int) ($routingOp?->return_buffer_days ?? 0),
                    'queue_threshold_enabled' => (bool) ($routingOp?->queue_threshold_enabled ?? $routingOp?->overlap_enabled ?? false),
                    'overlap_enabled' => (bool) ($routingOp?->queue_threshold_enabled ?? $routingOp?->overlap_enabled ?? false),
                    'transfer_batch_quantity' => (float) ($routingOp?->transfer_batch_quantity ?? 0.0000),
                    'transfer_lag_minutes' => (int) ($routingOp?->transfer_lag_minutes ?? 0),
                ]);
                $createdOps[] = $op;
            }

            // Bind sequential self-referencing operations dependency chain (previous_operation_id)
            for ($i = 1; $i < count($createdOps); $i++) {
                $createdOps[$i]->previous_operation_id = $createdOps[$i - 1]->id;
                $createdOps[$i]->save();
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

            // Fetch latest active BOM & Routing
            $bomId = $data['bom_id'] ?? null;
            $bom = $bomId
                ? ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($bomId)
                : ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('status', 'approved')
                    ->whereIn('bom_type', ['manufacturing', 'subcontracting'])
                    ->orderByRaw("CASE WHEN bom_type = 'manufacturing' THEN 1 WHEN bom_type = 'subcontracting' THEN 2 ELSE 3 END")
                    ->first();

            if (! $bom) {
                throw new InvalidArgumentException('Cannot create order: No approved Manufacturing or Subcontracting BOM exists for this product.');
            }

            // BOM Guardrails
            if ($bom->bom_type === 'engineering') {
                throw new InvalidArgumentException('Engineering BOMs cannot be selected for live Production Orders. Please convert or release as a Manufacturing BOM.');
            }
            if ($bom->bom_type === 'sales') {
                throw new InvalidArgumentException('Sales BOMs are sales kits and cannot generate manufacturing orders.');
            }

            $routingId = $data['routing_id'] ?? null;
            $routing = $routingId
                ? Routing::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($routingId)
                : Routing::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('status', 'active')
                    ->first();

            if (! $routing) {
                throw new InvalidArgumentException('Cannot create order: No active Routing exists for this product.');
            }

            $product = Product::find($productId);
            $defaultMode = $product ? $product->getDefaultProductionMode() : 'standard';
            $productionMode = !empty($data['production_mode']) ? $data['production_mode'] : $defaultMode;
            $productionModel = !empty($data['production_model'])
                ? $data['production_model']
                : ($product->default_production_model ?? ProductionOrder::MODEL_PURE_MANUFACTURING);

            $order = ProductionOrder::create([
                'tenant_id' => $tenantId,
                'order_number' => $this->numberService->generateNextNumber($tenantId),
                'production_plan_id' => null,
                'product_id' => $productId,
                'bom_id' => $bom->id,
                'routing_id' => $routing->id,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'sales_order_item_id' => $data['sales_order_item_id'] ?? null,
                'quantity_ordered' => $quantity,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'production_mode' => $productionMode,
                'production_model' => $productionModel,
                'status' => ProductionOrder::STATUS_DRAFT,
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);

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

            // 1. Resolve & Snapshot reservations directly from BOM items
            $itemsToResolve = [];
            foreach ($bom->items as $item) {
                $plannedQty = $item->quantity * ($quantity / ($bom->base_quantity ?: 1.0));

                // Add scrap factor if defined
                if ($item->material_scrap_percentage > 0) {
                    $plannedQty *= (1 + ($item->material_scrap_percentage / 100));
                }

                $this->createMaterialReservation($order, $item->id, $item->material_id, $plannedQty, $item->uom_id, $item->child_bom_id);

                $itemsToResolve[] = [
                    'product_id' => $item->material_id,
                    'planned_qty' => $plannedQty,
                    'uom_id' => $item->uom_id,
                    'child_bom_id' => $item->child_bom_id,
                ];
            }
            $this->createRequisitionSlip($order, $itemsToResolve);

            // 2. Resolve & Snapshot multi-level operations and dependencies recursively
            $this->snapshotMultiLevelRoutings($order, $bom, $routing, $quantity, $tenantId, $userId);

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Order Created',
                'title' => 'Production Order Created',
                'description' => "Production order {$order->order_number} has been created directly.",
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
     * Release order to shop floor execution.
     */
    public function release(int $id, ?int $userId = null, bool $force = false): void
    {
        try {
            $order = $this->orderRepository->find($id);
            abort_if(!$order, 404, 'Production Order not found.');

            if ($order->status !== ProductionOrder::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft orders can be released.');
            }

            if (!$force) {
                $latestSlip = $order->requisitionSlips()->latest('id')->first();
                if ($latestSlip) {
                    $slipStatusLower = strtolower($latestSlip->status ?? '');
                    $hasIssuedMaterial = in_array($slipStatusLower, ['fully issued', 'partially issued', 'completed', 'issued', 'partial']);

                    if (!$hasIssuedMaterial) {
                        throw new InvalidArgumentException('Cannot release order: Raw materials must be fully or partially issued by the store department first.');
                    }
                }
            }

            $order->status = ProductionOrder::STATUS_RELEASED;
            $order->released_by = $userId;
            $order->released_at = now();
            $order->save();

            // Initialize Work-in-Progress (WIP) tracking
            app(ProductionWipService::class)->initializeWip($order->id, null, $userId);

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
            dd("RELEASE_EXCEPTION", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
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
            $isOpDone = ($op->status === ProductionOrderOperation::STATUS_COMPLETED)
                || ($op->status === ProductionOrderOperation::STATUS_SKIPPED)
                || ($op->status === ProductionOrderOperation::STATUS_CANCELLED)
                || ($targetQty > 0 && $doneQty >= ($targetQty - 0.0001));

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
    public function close(int $id, ?int $userId = null): void
    {
        $order = $this->orderRepository->find($id);
        abort_if(!$order, 404, 'Production Order not found.');

        if (! $order->isCompleted()) {
            throw new InvalidArgumentException('Only completed orders can be closed.');
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
            $this->releaseInventoryReservations($order);

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
                    'cancelled_by' => $userId ?: 1,
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
        // Prevent duplicate reservation if already created for this order & product
        $existingRes = ProductionOrderReservation::where('production_order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingRes) {
            return $existingRes;
        }

        $warehouseId = $this->resolveReservationWarehouseId($order->tenant_id, $productId);

        $product = \App\Domains\Inventory\Models\Product::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->find($productId);

        $availableQty = 0.0;
        $effectiveReservationQty = $plannedQty;
        if ($product && ($product->type === 'semi_finished' || $childBomId)) {
            $availableQty = $warehouseId ? StockService::getAvailableStock($productId, $warehouseId) : 0.0;
            $effectiveReservationQty = min($plannedQty, $availableQty);
        }

        $reservation = null;
        if ($effectiveReservationQty > 0 || !($product && ($product->type === 'semi_finished' || $childBomId))) {
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
                        ->with(['items.material'])
                        ->first();
                }
                if (!$subBom) {
                    $subBom = ProductionBom::withoutGlobalScopes()
                        ->where('tenant_id', $order->tenant_id)
                        ->where('product_id', $productId)
                        ->where('status', 'approved')
                        ->with(['items.material'])
                        ->first();
                }

                if ($subBom && count($subBom->items) > 0) {
                    $baseQty = $subBom->base_quantity > 0 ? $subBom->base_quantity : 1.0;
                    $multiplier = $shortageQty / $baseQty;

                    foreach ($subBom->items as $subItem) {
                        if (!$subItem->material) continue;

                        $subPlannedQty = $subItem->quantity * $multiplier;
                        if ($subItem->material_scrap_percentage > 0) {
                            $subPlannedQty *= (1 + ($subItem->material_scrap_percentage / 100));
                        }

                        $this->createMaterialReservation(
                            $order,
                            $subItem->id,
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

    private function resolveReservationWarehouseId(int $tenantId, int $productId): ?int
    {
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
            $year = now()->format('Y');
            $prefix = "MR-{$year}-";
            $lastSlip = ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $order->tenant_id)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastSlip) {
                $lastNumStr = str_replace($prefix, '', $lastSlip->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $slipNotes = 'Ad-hoc Production Requirement for Order ' . $order->order_number;
            if (!empty($notes)) {
                $slipNotes .= ' — ' . $notes;
            }

            $slip = ProductionRequisitionSlip::create([
                'tenant_id' => $order->tenant_id,
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

            return $slip;
        });
    }

    private function createRequisitionSlip(ProductionOrder $order, array $itemsToResolve): void
    {
        $year = now()->format('Y');
        $prefix = "MR-{$year}-";
        $lastSlip = ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->where('requisition_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();
        $nextNum = 1;
        if ($lastSlip) {
            $lastNumStr = str_replace($prefix, '', $lastSlip->requisition_number);
            $nextNum = ((int) $lastNumStr) + 1;
        }
        $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        $slip = ProductionRequisitionSlip::create([
            'tenant_id' => $order->tenant_id,
            'production_order_id' => $order->id,
            'requisition_number' => $reqNumber,
            'status' => 'pending',
            'requisition_date' => now()->toDateString(),
            'notes' => 'Generated automatically from Production Order ' . $order->order_number,
        ]);

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

    private function createRequisitionSlipItemsRecursively(
        ProductionRequisitionSlip $slip,
        int $productId,
        float $plannedQty,
        int $uomId,
        ?int $childBomId = null
    ): void {
        $warehouseId = $this->resolveReservationWarehouseId($slip->tenant_id, $productId);
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
                    ->with(['items.material'])
                    ->first();
            } else {
                $subBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $slip->tenant_id)
                    ->where('product_id', $productId)
                    ->where('status', 'approved')
                    ->with(['items.material'])
                    ->first();
            }

            if ($subBom && count($subBom->items) > 0) {
                $baseQty = $subBom->base_quantity > 0 ? $subBom->base_quantity : 1.0;
                $multiplier = $shortageQty / $baseQty;

                foreach ($subBom->items as $subItem) {
                    if (!$subItem->material) continue;

                    $subPlannedQty = $subItem->quantity * $multiplier;
                    if ($subItem->material_scrap_percentage > 0) {
                        $subPlannedQty *= (1 + ($subItem->material_scrap_percentage / 100));
                    }

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
        int $uomId,
        ?int $warehouseId
    ): ProductionRequisitionSlipItem {
        return ProductionRequisitionSlipItem::create([
            'tenant_id' => $slip->tenant_id,
            'production_requisition_slip_id' => $slip->id,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_planned' => $plannedQty,
            'quantity_reserved' => 0.0000,
            'quantity_issued' => 0.0000,
            'uom_id' => $uomId,
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
        foreach ($routing->operations as $idx => $routingOp) {
            $status = ($idx === 0) ? ProductionOrderOperation::STATUS_READY : ProductionOrderOperation::STATUS_WAITING;

            $processingTime = ($routingOp->processing_time_minutes * $targetQty);
            $totalTime = $routingOp->setup_time_minutes + $processingTime;

            $op = ProductionOrderOperation::create([
                'tenant_id' => $tenantId,
                'production_order_id' => $order->id,
                'routing_operation_id' => $routingOp->id,
                'source_product_id' => $productId,
                'source_bom_id' => $bom->id,
                'source_routing_id' => $routing->id,
                'bom_level' => $level,
                'target_produced_qty' => $targetQty,
                'is_intermediate' => $isIntermediate,
                'quantity_claimed' => 0.0000,
                'sequence' => $routingOp->sequence,
                'operation_number' => $routingOp->operation_number,
                'name' => $routingOp->name,
                'work_center_id' => $routingOp->work_center_id,
                'machine_id' => $routingOp->machine_id,
                'status' => $status,
                'setup_time_planned' => $routingOp->setup_time_minutes,
                'processing_time_planned' => $processingTime,
                'total_time_planned' => $totalTime,
                'setup_time_actual' => 0.00,
                'processing_time_actual' => 0.00,
                'quantity_produced' => 0.0000,
                'quantity_rejected' => 0.0000,
                'quantity_scrapped' => 0.0000,
                'is_external' => (bool) (($routingOp->is_external ?? false) || in_array($order->production_model, ['complete_subcontracting', 'subcontract_company_material'])),
                'vendor_id' => $routingOp->vendor_id,
                'subcontract_lead_time_days' => (int) ($routingOp->subcontract_lead_time_days ?? 0),
                'subcontract_cost_per_unit' => (float) ($routingOp->subcontract_cost_per_unit ?? 0.0),
                'subcontract_service_product_id' => $routingOp->subcontract_service_product_id,
                'material_supply_type' => $routingOp->material_supply_type ?? ($order->production_model === 'complete_subcontracting' ? 'vendor_supplied' : 'company_supplied'),
                'dispatch_buffer_days' => (int) ($routingOp->dispatch_buffer_days ?? 0),
                'return_buffer_days' => (int) ($routingOp->return_buffer_days ?? 0),
                'queue_threshold_enabled' => (bool) ($routingOp->queue_threshold_enabled ?? $routingOp->overlap_enabled ?? false),
                'overlap_enabled' => (bool) ($routingOp->queue_threshold_enabled ?? $routingOp->overlap_enabled ?? false),
                'transfer_batch_quantity' => (float) ($routingOp->transfer_batch_quantity ?? 0.0000),
                'transfer_lag_minutes' => (int) ($routingOp->transfer_lag_minutes ?? 0),
            ]);
            $createdOps[] = $op;
        }

        // Bind intra-routing sequential dependencies (previous_operation_id)
        for ($i = 1; $i < count($createdOps); $i++) {
            $createdOps[$i]->previous_operation_id = $createdOps[$i - 1]->id;
            $createdOps[$i]->save();
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

        foreach ($bom->items as $item) {
            $childProductId = $item->material_id;
            $totalRequired = $item->quantity * $multiplier;
            if ($item->material_scrap_percentage > 0) {
                $totalRequired *= (1 + ($item->material_scrap_percentage / 100));
            }

            // Calculate available warehouse stock/reservation for child SFG (Clarification Rule 2)
            $reservedStock = (float) ProductWarehouseStock::where('tenant_id', $tenantId)
                ->where('product_id', $childProductId)
                ->sum('reserved_qty');
            if ($reservedStock > 0) {
                $warehouseAvailable = $reservedStock;
            } else {
                $warehouseId = $this->resolveReservationWarehouseId($tenantId, $childProductId);
                $warehouseAvailable = $warehouseId ? (float) StockService::getAvailableStock($childProductId, $warehouseId) : 0.0;
                if ($warehouseAvailable <= 0) {
                    $stockSum = ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('product_id', $childProductId)
                        ->sum('quantity');
                    if ($stockSum > 0) {
                        $warehouseAvailable = (float) $stockSum;
                    }
                }
            }

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
                    ->where('status', 'approved')
                    ->whereIn('bom_type', ['manufacturing', 'subcontracting'])
                    ->first();
            }

            if ($childBom && $netManufacturingQty > 0) {
                $childRouting = Routing::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $childProductId)
                    ->where('status', 'active')
                    ->first();

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
}
