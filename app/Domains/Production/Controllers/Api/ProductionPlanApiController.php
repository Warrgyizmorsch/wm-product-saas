<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\DTO\ProductionPlanDTO;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Requests\Api\StorePlanApiRequest;
use App\Domains\Production\Requests\Api\UpdatePlanApiRequest;
use App\Domains\Production\Resources\Api\ProductionOrderDetailResource;
use App\Domains\Production\Resources\Api\ProductionPlanDetailResource;
use App\Domains\Production\Resources\Api\ProductionPlanListResource;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * ProductionPlanApiController
 *
 * REST API controller for Production Planning and order generation.
 */
class ProductionPlanApiController extends ApiBaseController
{
    public function __construct(
        private readonly ProductionPlanService $planService,
        private readonly ProductionOrderService $orderService,
    ) {
    }

    /**
     * GET /api/v1/production/plans
     * List production plans (paginated, compact summary).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductionPlan::class);

        $tenantId = $this->getTenantId();

        $query = ProductionPlan::where('tenant_id', $tenantId)
            ->with(['product:id,name,sku']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('plan_number', 'like', $search)
                  ->orWhere('name', 'like', $search);
            });
        }

        $allowedSorts = ['created_at', 'plan_number', 'start_date', 'status'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $this->getPerPage($request);
        $paginator = $query->paginate($perPage);

        return $this->paginatedResponse($paginator, ProductionPlanListResource::class, 'Production plans retrieved successfully.');
    }

    /**
     * GET /api/v1/production/plans/{id}
     * Get detailed production plan.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $plan = ProductionPlan::where('tenant_id', $tenantId)
            ->with(['product.uom', 'bom', 'routing', 'productionOrders'])
            ->findOrFail($id);

        Gate::authorize('view', $plan);

        return $this->successResponse(
            new ProductionPlanDetailResource($plan),
            'Production plan details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/plans
     * Create a new draft production plan.
     */
    public function store(StorePlanApiRequest $request): JsonResponse
    {
        Gate::authorize('create', ProductionPlan::class);

        $tenantId = $this->getTenantId();

        try {
            $dto = ProductionPlanDTO::fromArray($request->validated());
            $plan = $this->planService->create($dto, $tenantId, auth()->id());

            return $this->createdResponse(
                new ProductionPlanDetailResource($plan->load('product')),
                'Production plan created successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/plans/{id}
     * Update an unapproved draft plan.
     */
    public function update(UpdatePlanApiRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $plan = ProductionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $plan);

        if ($plan->isFrozen()) {
            return $this->errorResponse('Frozen production plans cannot be edited.', 409);
        }

        try {
            $dto = ProductionPlanDTO::fromArray($request->validated());
            $this->planService->update($id, $dto, auth()->id());

            return $this->successResponse(
                new ProductionPlanDetailResource($plan->fresh(['product'])),
                'Production plan updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/plans/{id}/submit
     * Submit draft plan for approval.
     */
    public function submitApproval(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $plan = ProductionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $plan);

        try {
            $this->planService->submitApproval($id);

            return $this->successResponse(
                new ProductionPlanDetailResource($plan->fresh(['product'])),
                'Production plan submitted for approval.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/plans/{id}/approve
     * Approve a production plan.
     */
    public function approve(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $plan = ProductionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('approve', $plan);

        try {
            $this->planService->approve($id, auth()->id());

            return $this->successResponse(
                new ProductionPlanDetailResource($plan->fresh(['product'])),
                'Production plan approved successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/plans/{id}/release
     * Release an approved production plan.
     */
    public function release(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $plan = ProductionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('release', $plan);

        try {
            $this->planService->release($id, auth()->id());

            return $this->successResponse(
                new ProductionPlanDetailResource($plan->fresh(['product'])),
                'Production plan released successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/plans/{id}/create-order
     * Generate production order(s) from an approved plan.
     */
    public function createOrder(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $plan = ProductionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('release', $plan);

        try {
            $order = $this->orderService->createFromPlan($id, auth()->id());

            return $this->createdResponse(
                new ProductionOrderDetailResource($order->load('product')),
                "Production order {$order->order_number} generated from plan."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
