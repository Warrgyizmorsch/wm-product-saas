<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use App\Domains\Production\Requests\Api\StoreRoutingApiRequest;
use App\Domains\Production\Requests\Api\UpdateRoutingApiRequest;
use App\Domains\Production\Resources\Api\RoutingDetailResource;
use App\Domains\Production\Resources\Api\RoutingListResource;
use App\Domains\Production\Services\RoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RoutingApiController
 *
 * REST API controller for manufacturing Routings and operation sequences.
 */
class RoutingApiController extends ApiBaseController
{
    public function __construct(
        private readonly RoutingRepositoryInterface $routingRepository,
        private readonly RoutingService $routingService,
    ) {
    }

    /**
     * GET /api/v1/production/routings
     * List routings (paginated, compact summary).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Routing::class);

        $tenantId = $this->getTenantId();

        $query = Routing::where('tenant_id', $tenantId)
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
                $q->where('routing_number', 'like', $search)
                  ->orWhere('name', 'like', $search);
            });
        }

        $allowedSorts = ['created_at', 'routing_number', 'name', 'status'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $this->getPerPage($request);
        $paginator = $query->paginate($perPage);

        return $this->paginatedResponse($paginator, RoutingListResource::class, 'Routings retrieved successfully.');
    }

    /**
     * GET /api/v1/production/routings/{id}
     * Get detailed routing including operations.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $routing = Routing::where('tenant_id', $tenantId)
            ->with(['product.uom', 'operations.workCenter', 'operations.machine'])
            ->findOrFail($id);

        Gate::authorize('view', $routing);

        return $this->successResponse(
            new RoutingDetailResource($routing),
            'Routing details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/routings
     * Create a new draft routing.
     */
    public function store(StoreRoutingApiRequest $request): JsonResponse
    {
        Gate::authorize('create', Routing::class);

        try {
            $dto = RoutingDTO::fromArray($request->validated());
            $routing = $this->routingService->create($dto, auth()->id() ?: 1);

            return $this->createdResponse(
                new RoutingDetailResource($routing->load(['product', 'operations.workCenter'])),
                'Routing created successfully in draft.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/routings/{id}
     * Update an unapproved draft routing.
     */
    public function update(UpdateRoutingApiRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $routing);

        if ($routing->isReadOnly()) {
            return $this->errorResponse('Only draft routings can be edited.', 409);
        }

        try {
            $dto = RoutingDTO::fromArray($request->validated());
            $routing = $this->routingService->update($id, $dto);

            return $this->successResponse(
                new RoutingDetailResource($routing->fresh(['product', 'operations.workCenter'])),
                'Routing updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/routings/{id}/submit
     * Submit draft routing for approval.
     */
    public function submitApproval(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('submit', $routing);

        try {
            $this->routingService->submitApproval($id, auth()->id() ?: 1);

            return $this->successResponse(
                new RoutingDetailResource($routing->fresh(['product'])),
                'Routing submitted for approval.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/routings/{id}/approve
     * Approve a routing version.
     */
    public function approve(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('approve', $routing);

        try {
            $this->routingService->approve($id, auth()->id() ?: 1);

            return $this->successResponse(
                new RoutingDetailResource($routing->fresh(['product'])),
                'Routing approved and set as active.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/routings/{id}/duplicate
     * Duplicate a routing to create a new revision.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create', Routing::class);

        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)->findOrFail($id);

        $newVersion = $request->input('version');
        if (empty($newVersion)) {
            $currentVer = $routing->version ?: '1.0';
            $nextRev = (int) $routing->revision + 1;
            $newVersion = $currentVer . '.' . $nextRev;
            while (Routing::where('tenant_id', $tenantId)->where('product_id', $routing->product_id)->where('version', $newVersion)->exists()) {
                $nextRev++;
                $newVersion = $currentVer . '.' . $nextRev;
            }
        }

        try {
            $newRouting = $this->routingService->duplicateVersion($id, $newVersion, auth()->id() ?: 1);

            return $this->createdResponse(
                new RoutingDetailResource($newRouting->load(['product', 'operations.workCenter'])),
                "New routing revision created as draft."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/routings/{id}/reject
     * Reject a submitted routing.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('reject', $routing);

        $request->validate(['comments' => 'nullable|string|max:1000']);

        try {
            $this->routingService->reject($id, auth()->id() ?: 1, $request->input('comments'));

            return $this->successResponse(
                new RoutingDetailResource($routing->fresh(['product'])),
                'Routing rejected and returned to draft.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/routings/{id}/cancel
     * Cancel an active or draft routing.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('cancel', $routing);

        $request->validate(['comments' => 'nullable|string|max:1000']);

        try {
            $this->routingService->cancel($id, auth()->id() ?: 1, $request->input('comments'));

            return $this->successResponse(
                new RoutingDetailResource($routing->fresh(['product'])),
                'Routing cancelled.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * DELETE /api/v1/production/routings/{id}
     * Delete an unapproved draft routing.
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('delete', $routing);

        if (!$routing->isDraft()) {
            return $this->errorResponse('Only draft routings can be deleted.', 409);
        }

        try {
            $this->routingRepository->delete($id);

            return $this->successResponse(null, 'Draft routing deleted successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/production/routings/{id}/operations
     * Get operations sequence for a routing.
     */
    public function operations(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $routing = Routing::where('tenant_id', $tenantId)
            ->with([
                'operations' => fn($q) => $q->orderBy('sequence', 'asc'),
                'operations.workCenter:id,name,code',
                'operations.machine:id,name,code',
                'operations.materials.material:id,name,sku',
            ])
            ->findOrFail($id);

        Gate::authorize('view', $routing);

        return $this->successResponse(
            $routing->operations,
            'Routing operations retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/routings/import
     * Import routing master records from spreadsheet.
     */
    public function import(Request $request, \App\Domains\Production\Services\ProductionMasterImportService $importService): JsonResponse
    {
        Gate::authorize('create', Routing::class);

        $request->validate([
            'file'     => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
            'strategy' => 'nullable|string|in:create,update',
        ]);

        $strategy = $request->input('strategy', 'create');

        try {
            $result = $importService->import('routings', $request->file('file'), $strategy, $this->getTenantId(), auth()->id());

            if ($result['status'] === 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Import validation failed. No records were imported.',
                    'data'    => $result,
                ], 422);
            }

            return $this->successResponse($result, 'Routings imported successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/production/routings/export
     * Export routing master records.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', Routing::class);

        $tenantId = $this->getTenantId();
        $format = $request->query('format', 'xlsx');
        $fileName = 'routings_export.' . ($format === 'csv' ? 'csv' : 'xlsx');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\RoutingExport($tenantId, $request->all()),
            $fileName
        );
    }
}
