<?php

namespace App\Domains\Production\Services;

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

class ProductionOrderService
{
    public function __construct(
        private readonly ProductionOrderNumberService $numberService
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
                ]);
                $createdOps[] = $op;
            }

            // Bind sequential self-referencing operations dependency chain (previous_operation_id)
            for ($i = 1; $i < count($createdOps); $i++) {
                $createdOps[$i]->previous_operation_id = $createdOps[$i - 1]->id;
                $createdOps[$i]->save();
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
                    ->first();

            $routingId = $data['routing_id'] ?? null;
            $routing = $routingId
                ? Routing::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($routingId)
                : Routing::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('status', 'active')
                    ->first();

            if (! $bom || ! $routing) {
                throw new InvalidArgumentException('Cannot create order: No approved BOM and/or active Routing exists for this product.');
            }

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

                $this->createMaterialReservation($order, $item->id, $item->material_id, $plannedQty, $item->uom_id);

                $itemsToResolve[] = [
                    'product_id' => $item->material_id,
                    'planned_qty' => $plannedQty,
                    'uom_id' => $item->uom_id,
                    'child_bom_id' => $item->child_bom_id,
                ];
            }
            $this->createRequisitionSlip($order, $itemsToResolve);

            // 2. Resolve & Snapshot operations directly from Routing operations
            $createdOps = [];
            foreach ($routing->operations as $idx => $routingOp) {
                $status = ($idx === 0) ? ProductionOrderOperation::STATUS_READY : ProductionOrderOperation::STATUS_WAITING;

                $processingTime = ($routingOp->processing_time_minutes * $quantity);
                $totalTime = $routingOp->setup_time_minutes + $processingTime;

                $op = ProductionOrderOperation::create([
                    'tenant_id' => $tenantId,
                    'production_order_id' => $order->id,
                    'routing_operation_id' => $routingOp->id,
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
                ]);
                $createdOps[] = $op;
            }

            // Bind sequence dependencies
            for ($i = 1; $i < count($createdOps); $i++) {
                $createdOps[$i]->previous_operation_id = $createdOps[$i - 1]->id;
                $createdOps[$i]->save();
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
    public function release(int $id, ?int $userId = null): void
    {
        $order = ProductionOrder::findOrFail($id);

        if (! $order->isDraft()) {
            throw new InvalidArgumentException('Only draft orders can be released.');
        }

        $order->status = ProductionOrder::STATUS_RELEASED;
        $order->released_by = $userId;
        $order->released_at = now();
        $order->save();

        // Initialize Work-in-Progress (WIP) tracking
        app(ProductionWipService::class)->initializeWip($order->id, null, $userId);

        app(ProductionEventService::class)->writeEvent($order->tenant_id, [
            'production_order_id' => $order->id,
            'event_type' => 'Order Released',
            'title' => 'Production Order Released',
            'description' => "Production order {$order->order_number} released to the shop floor.",
            'severity' => 'success',
            'event_source' => 'ProductionOrderService',
            'triggered_by' => $userId,
        ]);
    }

    /**
     * Complete order execution.
     */
    public function complete(int $id, ?int $userId = null): void
    {
        $order = ProductionOrder::findOrFail($id);

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
     * Close order execution.
     */
    public function close(int $id, ?int $userId = null): void
    {
        $order = ProductionOrder::findOrFail($id);

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
        $order = ProductionOrder::findOrFail($id);

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
        int $uomId
    ): ProductionOrderReservation {
        $warehouseId = $this->resolveReservationWarehouseId($order->tenant_id, $productId);
        $reservedQty = 0.0;

        $reservation = ProductionOrderReservation::create([
            'tenant_id' => $order->tenant_id,
            'production_order_id' => $order->id,
            'bom_item_id' => $bomItemId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_planned' => $plannedQty,
            'quantity_reserved' => 0.0000,
            'quantity_issued' => 0.0000,
            'uom_id' => $uomId,
        ]);

        if ($warehouseId) {
            $availableQty = StockService::getAvailableStock($productId, $warehouseId);
            $reservedQty = min($plannedQty, $availableQty);

            if ($reservedQty > 0) {
                StockService::reserveStock(
                    $order->tenant_id,
                    $productId,
                    $warehouseId,
                    $reservedQty,
                    'Production Order',
                    $order->id,
                    $reservation->id
                );
            }
        }

        $reservation->update(['quantity_reserved' => $reservedQty]);

        return $reservation;
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
            if ($availableQty >= $plannedQty) {
                // Semi-finished item is fully available in warehouse, so request/reserve it directly
                $this->createRequisitionSlipItem($slip, $productId, $plannedQty, $uomId, $warehouseId);
                return;
            }

            // Semi-finished is not fully available.
            // 1. Request/reserve the available quantity first (if any)
            if ($availableQty > 0) {
                $this->createRequisitionSlipItem($slip, $productId, $availableQty, $uomId, $warehouseId);
            }

            // 2. Explode the shortage quantity to child BOM components
            $shortageQty = $plannedQty - $availableQty;
            
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
        $reservedQty = 0.0;

        $slipItem = ProductionRequisitionSlipItem::create([
            'tenant_id' => $slip->tenant_id,
            'production_requisition_slip_id' => $slip->id,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_planned' => $plannedQty,
            'quantity_reserved' => 0.0000,
            'quantity_issued' => 0.0000,
            'uom_id' => $uomId,
        ]);

        if ($warehouseId) {
            $availableQty = StockService::getAvailableStock($productId, $warehouseId);
            $reservedQty = min($plannedQty, $availableQty);

            if ($reservedQty > 0) {
                StockService::reserveStock(
                    $slip->tenant_id,
                    $productId,
                    $warehouseId,
                    $reservedQty,
                    'Production Order',
                    $slip->production_order_id,
                    $slipItem->id
                );
            }
        }

        $slipItem->update(['quantity_reserved' => $reservedQty]);

        return $slipItem;
    }
}
