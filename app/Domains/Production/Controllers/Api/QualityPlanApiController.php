<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Requests\StoreQualityPlanRequest;
use App\Domains\Production\Resources\Api\QualityPlanResource;
use App\Domains\Production\Services\QualityPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * QualityPlanApiController
 *
 * REST API for Quality Plans. Every write goes through QualityPlanService so the API follows the same lifecycle as the
 * screens (docs/production_quality_rules.md QR-07/08/10/11): draft → pending → approved → historical, automatic versions,
 * delete only in draft.
 */
class QualityPlanApiController extends ApiBaseController
{
    public function __construct(private readonly QualityPlanService $service)
    {
    }

    /**
     * GET /api/v1/production/quality-plans
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view', ProductionQualityPlan::class);

        $tenantId = $this->getTenantId();

        $query = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->with(['product:id,name,sku', 'workCenter:id,name,code']);

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('plan_number', 'like', $search);
            });
        }

        foreach (['type', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        foreach (['product_id', 'section_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (int) $request->query($filter));
            }
        }

        $paginator = $query->orderBy('id', 'desc')->paginate($this->getPerPage($request));

        return $this->paginatedResponse($paginator, QualityPlanResource::class, 'Quality plans retrieved successfully.');
    }

    /**
     * GET /api/v1/production/quality-plans/{id}
     */
    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', ProductionQualityPlan::class);

        $plan = ProductionQualityPlan::where('tenant_id', $this->getTenantId())
            ->with(['product:id,name,sku', 'workCenter:id,name,code', 'parameters'])
            ->findOrFail($id);

        return $this->successResponse(new QualityPlanResource($plan), 'Quality plan retrieved successfully.');
    }

    /**
     * POST /api/v1/production/quality-plans — always created as Draft version 1.
     */
    public function store(StoreQualityPlanRequest $request): JsonResponse
    {
        Gate::authorize('manage', ProductionQualityPlan::class);

        try {
            $plan = $this->service->create($this->getTenantId(), $request->validated(), (int) auth()->id());

            return $this->createdResponse(
                new QualityPlanResource($plan->load(['product', 'workCenter', 'parameters'])),
                'Quality plan created as draft.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/quality-plans/{id} — Draft: edited in place; Approved: creates the next version as Draft.
     */
    public function update(StoreQualityPlanRequest $request, int $id): JsonResponse
    {
        $plan = ProductionQualityPlan::where('tenant_id', $this->getTenantId())->findOrFail($id);
        Gate::authorize('update', $plan);

        try {
            $saved = $this->service->update($plan, $request->validated(), (int) auth()->id());

            return $this->successResponse(
                new QualityPlanResource($saved->load(['product', 'workCenter', 'parameters'])),
                $saved->id === $plan->id ? 'Quality plan updated.' : "Version {$saved->version} created as draft."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * DELETE /api/v1/production/quality-plans/{id} — Draft only (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = ProductionQualityPlan::where('tenant_id', $this->getTenantId())->findOrFail($id);
        Gate::authorize('delete', $plan);

        try {
            $this->service->delete($plan, (int) auth()->id());

            return $this->successResponse(null, 'Quality plan deleted.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /** POST /api/v1/production/quality-plans/{id}/submit */
    public function submit(int $id): JsonResponse
    {
        return $this->transition($id, 'submit', fn ($plan) => $this->service->submit($plan, (int) auth()->id()), 'Quality plan submitted for approval.');
    }

    /** POST /api/v1/production/quality-plans/{id}/approve */
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->transition($id, 'approve', fn ($plan) => $this->service->approve($plan, (int) auth()->id(), $request->input('comments')), 'Quality plan approved.');
    }

    /** POST /api/v1/production/quality-plans/{id}/reject */
    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->transition($id, 'reject', fn ($plan) => $this->service->reject($plan, (int) auth()->id(), $request->input('comments')), 'Quality plan rejected.');
    }

    /** POST /api/v1/production/quality-plans/{id}/historical */
    public function retire(Request $request, int $id): JsonResponse
    {
        return $this->transition($id, 'retire', fn ($plan) => $this->service->markHistorical($plan, (int) auth()->id(), $request->input('comments')), 'Quality plan marked historical.');
    }

    private function transition(int $id, string $ability, callable $action, string $message): JsonResponse
    {
        $plan = ProductionQualityPlan::where('tenant_id', $this->getTenantId())->findOrFail($id);
        Gate::authorize($ability, $plan);

        try {
            $result = $action($plan);

            return $this->successResponse(new QualityPlanResource($result->load(['product', 'workCenter', 'parameters'])), $message);
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
