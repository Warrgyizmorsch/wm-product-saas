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
            $payload = array_merge($request->validated(), [
                'tenant_id'  => $tenantId,
                'created_by' => auth()->id(),
                'status'     => $request->input('status', ProductionOrder::STATUS_DRAFT),
            ]);

            $order = $orderService->create($payload);

            return $this->createdResponse(
                new ProductionOrderDetailResource($order->load('product')),
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
                (float) $request->validated('quantity'),
                $request->validated('reason'),
                auth()->id(),
                null,
                (bool) $request->boolean('create_ncr'),
                $request->filled('ncr_category') ? ['category' => $request->validated('ncr_category')] : []
            );

            return $this->successResponse(null, 'Production scrap logged successfully.');
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
}
