<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\DTO\MachineDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Repositories\MachineRepositoryInterface;
use App\Domains\Production\Requests\Api\StoreMachineApiRequest;
use App\Domains\Production\Requests\Api\UpdateMachineApiRequest;
use App\Domains\Production\Resources\Api\MachineResource;
use App\Domains\Production\Services\MachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * MachineApiController
 *
 * REST API controller for Machine master data, work center assignment, and status.
 */
class MachineApiController extends ApiBaseController
{
    public function __construct(
        private readonly MachineRepositoryInterface $repository,
        private readonly MachineService $service,
    ) {
    }

    /**
     * GET /api/v1/production/machines
     * List machines (paginated, filterable by work_center_id, status, etc.).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Machine::class);

        $tenantId = $this->getTenantId();

        $query = Machine::where('tenant_id', $tenantId)
            ->with(['workCenter:id,name,code']);

        if ($request->filled('work_center_id')) {
            $query->where('work_center_id', (int) $request->query('work_center_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('current_state')) {
            $query->where('current_state', $request->query('current_state'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhere('model_number', 'like', $search);
            });
        }

        $perPage = $this->getPerPage($request);
        $paginator = $query->orderBy('name')->paginate($perPage);

        return $this->paginatedResponse($paginator, MachineResource::class, 'Machines retrieved successfully.');
    }

    /**
     * GET /api/v1/production/machines/{id}
     * Get detailed machine.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $machine = Machine::where('tenant_id', $tenantId)
            ->with('workCenter:id,name,code')
            ->findOrFail($id);

        Gate::authorize('view', $machine);

        return $this->successResponse(
            new MachineResource($machine),
            'Machine details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/machines
     * Create a new machine.
     */
    public function store(StoreMachineApiRequest $request): JsonResponse
    {
        Gate::authorize('create', Machine::class);

        $tenantId = $this->getTenantId();

        try {
            $dto = MachineDTO::fromArray($request->validated());
            $machine = $this->service->create($dto, $tenantId);

            return $this->createdResponse(
                new MachineResource($machine),
                'Machine created successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/machines/{id}
     * Update a machine.
     */
    public function update(UpdateMachineApiRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $machine = Machine::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $machine);

        try {
            $dto = MachineDTO::fromArray($request->validated());
            $this->service->update($id, $dto);

            return $this->successResponse(
                new MachineResource($machine->fresh()),
                'Machine updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
