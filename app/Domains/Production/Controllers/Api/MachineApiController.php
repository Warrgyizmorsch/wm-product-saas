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

    /**
     * DELETE /api/v1/production/machines/{id}
     * Delete an unreferenced machine.
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $machine = Machine::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('delete', $machine);

        try {
            $this->service->delete($id);

            return $this->successResponse(null, 'Machine deleted successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/machines/{id}/link-asset
     * Link an existing Fixed Asset to a machine.
     */
    public function linkAsset(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $machine = Machine::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $machine);

        $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
        ]);

        try {
            $updated = $this->service->linkAsset($id, (int) $request->input('asset_id'), $tenantId);

            return $this->successResponse(
                new MachineResource($updated),
                'Fixed asset linked to machine successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/machines/{id}/unlink-asset
     * Unlink a Fixed Asset from a machine.
     */
    public function unlinkAsset(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $machine = Machine::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $machine);

        try {
            $updated = $this->service->unlinkAsset($id, $tenantId);

            return $this->successResponse(
                new MachineResource($updated),
                'Fixed asset unlinked from machine successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/machines/import
     * Import machine master records from spreadsheet.
     */
    public function import(Request $request, \App\Domains\Production\Services\ProductionMasterImportService $importService): JsonResponse
    {
        Gate::authorize('create', Machine::class);

        $request->validate([
            'file'     => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
            'strategy' => 'nullable|string|in:create,update',
        ]);

        $strategy = $request->input('strategy', 'create');

        try {
            $result = $importService->import('machines', $request->file('file'), $strategy, $this->getTenantId(), auth()->id());

            if ($result['status'] === 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Import validation failed. No records were imported.',
                    'data'    => $result,
                ], 422);
            }

            return $this->successResponse($result, 'Machines imported successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/production/machines/export
     * Export machine master records.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', Machine::class);

        $tenantId = $this->getTenantId();
        $format = $request->query('format', 'xlsx');
        $fileName = 'machines_export.' . ($format === 'csv' ? 'csv' : 'xlsx');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\MachineExport($tenantId, $request->all()),
            $fileName
        );
    }
}
