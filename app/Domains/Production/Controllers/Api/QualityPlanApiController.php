<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Requests\StoreQualityPlanRequest;
use App\Domains\Production\Resources\Api\QualityPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * QualityPlanApiController
 *
 * REST API controller for Production Quality Assurance Plans.
 */
class QualityPlanApiController extends ApiBaseController
{
    /**
     * GET /api/v1/production/quality-plans
     * List quality plans with pagination and filtering.
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
                  ->orWhere('code', 'like', $search);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('work_center_id')) {
            $query->where('work_center_id', (int) $request->query('work_center_id'));
        }

        $perPage = $this->getPerPage($request);
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($paginator, QualityPlanResource::class, 'Quality plans retrieved successfully.');
    }

    /**
     * GET /api/v1/production/quality-plans/{id}
     * Get a single quality plan with its parameters.
     */
    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', ProductionQualityPlan::class);

        $tenantId = $this->getTenantId();

        $plan = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->with(['product:id,name,sku', 'workCenter:id,name,code', 'parameters'])
            ->findOrFail($id);

        return $this->successResponse(
            new QualityPlanResource($plan),
            'Quality plan retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/quality-plans
     * Create a new quality plan and its inspection parameters.
     */
    public function store(StoreQualityPlanRequest $request): JsonResponse
    {
        Gate::authorize('manage', ProductionQualityPlan::class);

        $tenantId = $this->getTenantId();
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'draft';

        if ($data['status'] === 'approved') {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        try {
            $plan = DB::transaction(function () use ($tenantId, $data) {
                $plan = ProductionQualityPlan::create($data);

                foreach ($data['parameters'] as $param) {
                    $param['tenant_id'] = $tenantId;
                    $param['quality_plan_id'] = $plan->id;
                    $param['is_mandatory'] = filter_var($param['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    ProductionQualityPlanParameter::create($param);
                }

                return $plan;
            });

            return $this->createdResponse(
                new QualityPlanResource($plan->load(['product', 'workCenter', 'parameters'])),
                'Quality plan created successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/quality-plans/{id}
     * Update an existing quality plan and replace parameters.
     */
    public function update(StoreQualityPlanRequest $request, int $id): JsonResponse
    {
        Gate::authorize('manage', ProductionQualityPlan::class);

        $tenantId = $this->getTenantId();
        $plan = ProductionQualityPlan::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validated();

        if (isset($data['status']) && $data['status'] === 'approved' && $plan->status !== 'approved') {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        try {
            DB::transaction(function () use ($tenantId, $plan, $data) {
                $plan->update($data);

                ProductionQualityPlanParameter::where('quality_plan_id', $plan->id)->delete();

                foreach ($data['parameters'] as $param) {
                    $param['tenant_id'] = $tenantId;
                    $param['quality_plan_id'] = $plan->id;
                    $param['is_mandatory'] = filter_var($param['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    ProductionQualityPlanParameter::create($param);
                }
            });

            return $this->successResponse(
                new QualityPlanResource($plan->fresh(['product', 'workCenter', 'parameters'])),
                'Quality plan updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * DELETE /api/v1/production/quality-plans/{id}
     * Delete a quality plan and associated parameters.
     */
    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('manage', ProductionQualityPlan::class);

        $tenantId = $this->getTenantId();
        $plan = ProductionQualityPlan::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            DB::transaction(function () use ($plan) {
                ProductionQualityPlanParameter::where('quality_plan_id', $plan->id)->delete();
                $plan->delete();
            });

            return $this->successResponse(null, 'Quality plan deleted successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
