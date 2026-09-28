<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Requests\Api\IssueMaterialApiRequest;
use App\Domains\Production\Requests\Api\LogProgressApiRequest;
use App\Domains\Production\Requests\Api\LogScrapApiRequest;
use App\Domains\Production\Requests\Api\ReceiveFgApiRequest;
use App\Domains\Production\Requests\Api\StoreProductionOrderApiRequest;
use App\Domains\Production\Requests\Api\UpdateProductionOrderApiRequest;
use App\Domains\Production\Resources\Api\ProductionOrderDetailResource;
use App\Domains\Production\Resources\Api\ProductionOrderListResource;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * ProductionOrderApiController
 *
 * REST API controller for Production Orders and lifecycle state transitions.
 * Reuses existing Production services and repository abstractions.
 */
class ProductionOrderApiController extends ApiBaseController
{
    /**
     * GET /api/v1/production/orders
     * List production orders (paginated, compact summary).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductionOrder::class);

        $tenantId = $this->getTenantId();

        $query = ProductionOrder::where('tenant_id', $tenantId)
            ->with(['product:id,name,sku']);

        // Controlled Whitelisted Filters
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('production_model')) {
            $query->where('production_model', $request->query('production_model'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('start_date', '<=', $request->query('to_date'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        // Whitelisted Sorting
        $allowedSorts = ['created_at', 'order_number', 'start_date', 'end_date', 'status'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $this->getPerPage($request);
        $paginator = $query->paginate($perPage);

        return $this->paginatedResponse($paginator, ProductionOrderListResource::class, 'Production orders retrieved successfully.');
    }

    /**
     * GET /api/v1/production/orders/{id}
     * Get detailed view of a production order.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $order = ProductionOrder::where('tenant_id', $tenantId)
            ->with([
                'product.uom',
                'bom:id,bom_number,version,base_quantity',
                'routing:id,routing_number,name',
                'operations.workCenter:id,name,code',
                'operations.machine:id,name,code,current_state',
                'reservations.product:id,name,sku',
            ])
            ->findOrFail($id);

        Gate::authorize('view', $order);

        return $this->successResponse(
            new ProductionOrderDetailResource($order),
            'Production order details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/orders
     * Create a new draft production order.
     */
    public function store(StoreProductionOrderApiRequest $request, ProductionOrderService $orderService): JsonResponse
    {
        Gate::authorize('create', ProductionOrder::class);

        $tenantId = $this->getTenantId();

        try {
            $order = $orderService->createDirect($request->validated(), $tenantId, auth()->id());

            return $this->createdResponse(
                new ProductionOrderDetailResource($order->load([
                    'product.uom',
                    'bom',
                    'routing',
                    'operations.workCenter',
                    'operations.machine',
                    'reservations.product',
                ])),
                'Production order created successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/orders/{id}
     * Update an unreleased / draft production order.
     */
    public function update(UpdateProductionOrderApiRequest $request, int $id, ProductionOrderService $orderService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $order);

        if ($order->isFrozen() || !in_array($order->status, [ProductionOrder::STATUS_DRAFT, ProductionOrder::STATUS_RELEASED])) {
            return $this->errorResponse('Cannot edit orders that are completed, closed, or cancelled.', 409);
        }

        try {
            $orderService->update($id, $request->validated());

            return $this->successResponse(
                new ProductionOrderDetailResource($order->fresh(['product', 'bom', 'routing'])),
                'Production order updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/release
     * Release a production order to the shop floor.
     */
    public function release(int $id, ProductionOrderService $orderService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('release', $order);

        try {
            $orderService->release($id, auth()->id());

            return $this->successResponse(
                new ProductionOrderDetailResource($order->fresh(['product'])),
                "Production order {$order->order_number} released successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/issue-material
     * Issue reserved raw materials to a production order.
     */
    public function issueMaterial(IssueMaterialApiRequest $request, int $id, ProductionMaterialService $materialService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('issue', $order);

        // Enforce that the reservation belongs to this tenant and order
        ProductionOrderReservation::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->findOrFail($request->validated('reservation_id'));

        try {
            $materialService->issueMaterial(
                (int) $request->validated('reservation_id'),
                (float) $request->validated('quantity'),
                $request->validated('remarks'),
                auth()->id(),
                $request->filled('warehouse_id') ? (int) $request->validated('warehouse_id') : null
            );

            return $this->successResponse(null, 'Material issued successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/log-progress
     * Log operation progress / intermediate output.
     */
    public function logProgress(LogProgressApiRequest $request, int $id, ProductionExecutionService $executionService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('logProgress', $order);

        // Enforce operation belongs to this tenant and order
        ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->findOrFail($request->validated('operation_id'));

        try {
            $executionService->logProgress(
                (int) $request->validated('operation_id'),
                (float) $request->validated('quantity_produced'),
                (float) ($request->validated('quantity_rejected') ?? 0),
                (float) ($request->validated('quantity_scrapped') ?? 0),
                (float) ($request->validated('setup_minutes_logged') ?? 0),
                (float) ($request->validated('run_minutes_logged') ?? 0),
                $request->validated('remarks'),
                $request->validated('machine_id'),
                auth()->id(),
                (bool) $request->validated('complete_operation', false),
                null,
                $request->filled('batch_id') ? (int) $request->validated('batch_id') : null
            );

            return $this->successResponse(null, 'Operation progress logged successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/log-scrap
     * Log production scrap.
     */
    public function logScrap(LogScrapApiRequest $request, int $id, ProductionExecutionService $executionService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('logProgress', $order);

        if ($request->filled('operation_id')) {
            ProductionOrderOperation::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->findOrFail($request->validated('operation_id'));
        }

        try {
            $executionService->logScrap(
                $id,
                $request->validated('operation_id'),
                $request->validated('product_id'),
                (float) ($request->validated('quantity') ?? 0),
                $request->validated('reason'),
                auth()->id(),
                null,
                (bool) $request->boolean('create_ncr'),
                $request->filled('ncr_category') ? ['category' => $request->validated('ncr_category')] : [],
                null,
                $request->validated()
            );

            return $this->successResponse(null, 'Production scrap logged successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/remnants
     * Register a reusable remnant / offcut from this production order.
     */
    public function registerRemnant(Request $request, int $id, \App\Domains\Inventory\Services\RemnantInventoryService $remnantService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('issue', $order);

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'measurement_type' => 'required|string|in:linear,sheet,weight,count',
            'length' => 'nullable|numeric|min:0.01',
            'width' => 'nullable|numeric|min:0.01',
            'thickness' => 'nullable|numeric|min:0.01',
            'weight' => 'nullable|numeric|min:0.01',
            'weight_unit' => 'nullable|string|in:kg,g',
            'pieces' => 'nullable|integer|min:1',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'warehouse_location' => 'nullable|string|max:100',
            'operation_id' => 'nullable|integer|exists:production_order_operations,id',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $remnant = $remnantService->registerRemnant($tenantId, [
                'product_id' => (int) $validated['product_id'],
                'measurement_type' => $validated['measurement_type'],
                'length' => isset($validated['length']) ? (float) $validated['length'] : null,
                'width' => isset($validated['width']) ? (float) $validated['width'] : null,
                'thickness' => isset($validated['thickness']) ? (float) $validated['thickness'] : null,
                'weight' => isset($validated['weight']) ? (float) $validated['weight'] : null,
                'weight_unit' => $validated['weight_unit'] ?? null,
                'pieces' => (int) ($validated['pieces'] ?? 1),
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'warehouse_location' => $validated['warehouse_location'] ?? null,
                'source_production_order_id' => $order->id,
                'source_production_order_operation_id' => $validated['operation_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], auth()->id());

            return $this->successResponse([
                'id' => $remnant->id,
                'remnant_code' => $remnant->remnant_code,
                'status' => $remnant->status,
                'measurement_type' => $remnant->measurement_type,
                'canonical_quantity' => $remnant->current_quantity,
                'total_valuation' => $remnant->total_valuation,
            ], 'Reusable remnant registered successfully.', 201);
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/allocate-remnant
     * Allocate one or more remnants to a production order requirement.
     */
    public function allocateRemnant(Request $request, int $id, \App\Domains\Production\Services\RemnantAllocationService $allocationService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('issue', $order);

        $validated = $request->validate([
            'reservation_id' => 'required|integer|exists:production_order_reservations,id',
            'allocations' => 'required|array|min:1',
            'allocations.*.remnant_id' => 'required|integer|exists:inventory_remnants,id',
            'allocations.*.allocated_length' => 'nullable|numeric|min:0.01',
            'allocations.*.allocated_quantity' => 'nullable|numeric|min:0.0001',
        ]);

        try {
            $allocations = $allocationService->allocateRemnants(
                $tenantId,
                $order->id,
                (int) $validated['reservation_id'],
                $validated['allocations'],
                auth()->id()
            );

            return $this->successResponse(
                collect($allocations)->map(fn($a) => [
                    'id' => $a->id,
                    'remnant_id' => $a->remnant_id,
                    'allocated_quantity' => $a->allocated_quantity,
                    'allocated_length' => $a->allocated_length,
                    'status' => $a->status,
                ])->toArray(),
                'Remnants allocated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/consume-remnant/{allocation}
     * Physically consume an allocated remnant for this production order.
     */
    public function consumeRemnant(Request $request, int $id, int $allocationId, \App\Domains\Production\Services\RemnantAllocationService $allocationService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('issue', $order);

        $validated = $request->validate([
            'consumed_length' => 'nullable|numeric|min:0.01',
            'consumed_quantity' => 'nullable|numeric|min:0.0001',
        ]);

        try {
            $allocation = \App\Domains\Production\Models\ProductionOrderRemnantAllocation::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->findOrFail($allocationId);

            $consumption = $allocationService->consumeAllocatedRemnant(
                $allocation->id,
                isset($validated['consumed_length']) ? (float) $validated['consumed_length'] : null,
                isset($validated['consumed_quantity']) ? (float) $validated['consumed_quantity'] : null,
                auth()->id()
            );

            return $this->successResponse([
                'consumption_id' => $consumption->id,
                'remnant_id' => $consumption->remnant_id,
                'consumed_quantity' => $consumption->consumed_quantity,
                'consumed_length' => $consumption->consumed_length,
                'remaining_quantity' => $consumption->remaining_quantity,
                'remaining_length' => $consumption->remaining_length,
                'event_type' => $consumption->event_type,
            ], 'Allocated remnant consumed successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/receive-fg
     * Receive finished goods from production order into warehouse stock.
     */
    public function receiveFg(ReceiveFgApiRequest $request, int $id, ProductionExecutionService $executionService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('receiveFg', $order);

        try {
            $executionService->receiveFinishedGoods(
                $id,
                (float) $request->validated('quantity_received'),
                $request->validated('quality_status'),
                $request->validated('remarks'),
                auth()->id(),
                $request->filled('warehouse_id') ? (int) $request->validated('warehouse_id') : null
            );

            return $this->successResponse(null, 'Finished goods received successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/complete
     * Formally complete a production order.
     */
    public function complete(int $id, ProductionOrderService $orderService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('complete', $order);

        try {
            $orderService->complete($id, auth()->id());

            return $this->successResponse(
                new ProductionOrderDetailResource($order->fresh(['product'])),
                "Production order {$order->order_number} completed successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/cancel
     * Cancel a production order.
     */
    public function cancel(int $id, ProductionOrderService $orderService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('cancel', $order);

        try {
            $orderService->cancel($id, auth()->id());

            return $this->successResponse(
                new ProductionOrderDetailResource($order->fresh(['product'])),
                "Production order {$order->order_number} cancelled successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/close
     * Close and archive a completed production order.
     */
    public function close(int $id, ProductionOrderService $orderService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('close', $order);

        try {
            $orderService->close($id, auth()->id());

            return $this->successResponse(
                new ProductionOrderDetailResource($order->fresh(['product'])),
                "Production order {$order->order_number} closed and archived successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/return-material
     * Return issued raw material back to warehouse inventory.
     */
    public function returnMaterial(Request $request, int $id, ProductionMaterialService $materialService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('return', $order);

        $validated = $request->validate([
            'reservation_id' => 'required|exists:production_order_reservations,id',
            'warehouse_id'   => 'nullable|exists:warehouses,id',
            'quantity'       => 'required|numeric|min:0.0001',
            'remarks'        => 'nullable|string|max:255',
        ]);

        ProductionOrderReservation::where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->findOrFail($validated['reservation_id']);

        try {
            $materialService->returnMaterial(
                (int) $validated['reservation_id'],
                (float) $validated['quantity'],
                $validated['remarks'] ?? null,
                auth()->id(),
                isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null
            );

            return $this->successResponse(null, 'Material returned to warehouse successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/request-additional-material
     * Submit an ad-hoc requisition request for additional materials.
     */
    public function requestAdditionalMaterial(Request $request, int $id, ProductionOrderService $orderService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('issue', $order);

        if ($order->isCompleted() || $order->isClosed() || $order->isCancelled()) {
            return $this->errorResponse('Cannot request additional material for a completed, closed, or cancelled order.', 422);
        }

        $validated = $request->validate([
            'items'                     => 'required|array|min:1',
            'items.*.product_id'        => 'required|integer|exists:products,id',
            'items.*.quantity'          => 'required|numeric|gt:0',
            'items.*.notes'             => 'nullable|string|max:255',
            'notes'                     => 'nullable|string|max:500',
        ]);

        try {
            $slip = $orderService->createAdHocRequisitionSlip(
                $order,
                $validated['items'],
                auth()->id(),
                $validated['notes'] ?? null
            );

            return $this->createdResponse([
                'requisition_id'     => $slip->id,
                'requisition_number' => $slip->requisition_number,
                'status'             => $slip->status,
                'items_count'        => count($validated['items']),
            ], "Request sent to store for additional materials (Requisition #{$slip->requisition_number}).");
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/rework
     * Register a rework loop on an operation.
     */
    public function logRework(Request $request, int $id, ProductionExecutionService $executionService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('logProgress', $order);

        $validated = $request->validate([
            'operation_id' => 'nullable|exists:production_order_operations,id',
            'quantity'     => 'required|numeric|min:0.0001',
            'reason'       => 'nullable|string|max:255',
        ]);

        if (!empty($validated['operation_id'])) {
            ProductionOrderOperation::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->findOrFail($validated['operation_id']);
        }

        try {
            $executionService->logRework(
                $id,
                isset($validated['operation_id']) ? (int) $validated['operation_id'] : null,
                (float) $validated['quantity'],
                $validated['reason'] ?? null,
                auth()->id()
            );

            return $this->successResponse(null, 'Rework loop registered successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/orders/{id}/release-remnant-allocation/{allocation}
     * Release a remnant reservation/allocation back to inventory.
     */
    public function releaseRemnantAllocation(int $id, int $allocationId, \App\Domains\Production\Services\RemnantAllocationService $allocationService): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('issue', $order);

        try {
            $allocation = \App\Domains\Production\Models\ProductionOrderRemnantAllocation::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->findOrFail($allocationId);

            $allocationService->releaseAllocation($allocation->id, auth()->id());

            return $this->successResponse(null, 'Remnant allocation released successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/production/orders/export
     * Export production orders.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', ProductionOrder::class);

        $tenantId = $this->getTenantId();
        $format = $request->query('format', 'xlsx');
        $fileName = 'production_orders_export.' . ($format === 'csv' ? 'csv' : 'xlsx');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductionOrderExport($tenantId, $request->all()),
            $fileName
        );
    }
}
