<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\DTO\WorkCenterDTO;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use App\Domains\Production\Requests\Api\StoreWorkCenterApiRequest;
use App\Domains\Production\Requests\Api\UpdateWorkCenterApiRequest;
use App\Domains\Production\Resources\Api\WorkCenterResource;
use App\Domains\Production\Services\WorkCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * WorkCenterApiController
 *
 * REST API controller for Work Center master data and capacity management.
 */
class WorkCenterApiController extends ApiBaseController
{
    public function __construct(
        private readonly WorkCenterRepositoryInterface $repository,
        private readonly WorkCenterService $service,
    ) {
    }

    /**
     * GET /api/v1/production/work-centers
     * List work centers (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkCenter::class);

        $tenantId = $this->getTenantId();

        $query = WorkCenter::where('tenant_id', $tenantId)
            ->withCount('machines');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('work_center_type')) {
            $query->where('work_center_type', $request->query('work_center_type'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }

        $perPage = $this->getPerPage($request);
        $paginator = $query->orderBy('name')->paginate($perPage);

        return $this->paginatedResponse($paginator, WorkCenterResource::class, 'Work centers retrieved successfully.');
    }

    /**
     * GET /api/v1/production/work-centers/{id}
     * Get detailed work center.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $workCenter = WorkCenter::where('tenant_id', $tenantId)
            ->with('machines')
            ->findOrFail($id);

        Gate::authorize('view', $workCenter);

        return $this->successResponse(
            new WorkCenterResource($workCenter),
            'Work center details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/work-centers
     * Create a new work center.
     */
    public function store(StoreWorkCenterApiRequest $request): JsonResponse
    {
        Gate::authorize('create', WorkCenter::class);

        $tenantId = $this->getTenantId();

        try {
            $dto = WorkCenterDTO::fromArray($request->validated());
            $workCenter = $this->service->create($dto, $tenantId);

            return $this->createdResponse(
                new WorkCenterResource($workCenter),
                'Work center created successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/work-centers/{id}
     * Update a work center.
     */
    public function update(UpdateWorkCenterApiRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $workCenter = WorkCenter::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $workCenter);

        try {
            $dto = WorkCenterDTO::fromArray($request->validated());
            $this->service->update($id, $dto);

            return $this->successResponse(
                new WorkCenterResource($workCenter->fresh()),
                'Work center updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
