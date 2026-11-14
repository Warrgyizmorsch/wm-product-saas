<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Repositories\ProductionBomRepositoryInterface;
use App\Domains\Production\Requests\Api\StoreBomApiRequest;
use App\Domains\Production\Requests\Api\UpdateBomApiRequest;
use App\Domains\Production\Resources\Api\ProductionBomDetailResource;
use App\Domains\Production\Resources\Api\ProductionBomListResource;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\ProductionBomVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * ProductionBomApiController
 *
 * REST API controller for Bill of Materials (BOM) master data and revisions.
 */
class ProductionBomApiController extends ApiBaseController
{
    public function __construct(
        private readonly ProductionBomRepositoryInterface $bomRepository,
        private readonly ProductionBomService $bomService,
        private readonly ProductionBomVersionService $versionService,
    ) {
    }

    /**
     * GET /api/v1/production/boms
     * List Bill of Materials (paginated, compact summary).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductionBom::class);

        $tenantId = $this->getTenantId();

        $query = ProductionBom::where('tenant_id', $tenantId)
            ->with(['product:id,name,sku']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('bom_type')) {
            $query->where('bom_type', $request->query('bom_type'));
        }

        if ($request->filled('usage_context')) {
            $query->where('usage_context', $request->query('usage_context'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('bom_number', 'like', $search)
                  ->orWhere('bom_name', 'like', $search);
            });
        }

        $allowedSorts = ['created_at', 'bom_number', 'effective_date', 'status'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $this->getPerPage($request);
        $paginator = $query->paginate($perPage);

        return $this->paginatedResponse($paginator, ProductionBomListResource::class, 'BOMs retrieved successfully.');
    }

    /**
     * Eager load relations needed by ProductionBomDetailResource.
     */
    private function detailRelations(): array
    {
        return [
            'product.uom',
            'baseUom',
            'routing.operations',
            'operationMaterials.material.uom',
            'operationMaterials.uom',
            'operationMaterials.routingOperation' => fn ($q) => $q->withTrashed(),
            'items.material.uom',
            'items.product',
        ];
    }

    /**
     * GET /api/v1/production/boms/{id}
     * Get detailed BOM including component lines.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $bom = ProductionBom::where('tenant_id', $tenantId)
            ->with($this->detailRelations())
            ->findOrFail($id);

        Gate::authorize('view', $bom);

        return $this->successResponse(
            new ProductionBomDetailResource($bom),
            'BOM details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/boms
     * Create a new draft BOM.
     */
    public function store(StoreBomApiRequest $request): JsonResponse
    {
        Gate::authorize('create', ProductionBom::class);

        try {
            $dto = ProductionBomDTO::fromArray($request->validated());
            $bom = $this->bomService->create($dto, auth()->id());

            return $this->createdResponse(
                new ProductionBomDetailResource($bom->load($this->detailRelations())),
                'BOM created successfully in draft mode.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/boms/{id}
     * Update an unapproved draft BOM.
     */
    public function update(UpdateBomApiRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $bom = ProductionBom::where('tenant_id', $tenantId)
            ->with(['operationMaterials', 'items'])
            ->findOrFail($id);

        Gate::authorize('update', $bom);

        if (!$bom->isDraft() && !$bom->isUnderRevision()) {
            return $this->errorResponse('Approved BOMs cannot be edited directly.', 409);
        }

        try {
            // BM-IMPL-19 #2: no `items` → header-only update; allocations and master rows are never touched
            if (!$request->has('items')) {
                $this->bomService->updateHeader($id, $request->validated());

                return $this->successResponse(
                    new ProductionBomDetailResource($bom->fresh($this->detailRelations())),
                    'BOM updated successfully.'
                );
            }

            $existingItems = [];
            $mergedData = array_merge([
                'bom_number'     => $bom->bom_number,
                'product_id'     => $bom->product_id,
                'bom_type'       => $bom->bom_type,
                'base_quantity'  => $bom->base_quantity,
                'base_uom_id'    => $bom->base_uom_id,
                'version'        => $bom->version,
                'routing_id'     => $bom->routing_id,
                'effective_date' => $bom->effective_date ? \Carbon\Carbon::parse($bom->effective_date)->toDateString() : now()->toDateString(),
                'expiry_date'    => $bom->expiry_date ? \Carbon\Carbon::parse($bom->expiry_date)->toDateString() : null,
                'usage_context'  => $bom->usage_context,
                'items'          => $existingItems,
            ], $request->validated());

            $dto = ProductionBomDTO::fromArray($mergedData);
            $this->bomService->update($id, $dto);

            return $this->successResponse(
                new ProductionBomDetailResource($bom->fresh($this->detailRelations())),
                'BOM updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/boms/{id}/submit
     * Submit draft BOM for approval.
     */
    public function submitApproval(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $bom = ProductionBom::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $bom);

        try {
            $this->bomService->submitApproval($id);

            return $this->successResponse(
                new ProductionBomDetailResource($bom->fresh($this->detailRelations())),
                'BOM submitted for approval.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/boms/{id}/approve
     * Approve a BOM version.
     */
    public function approve(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $bom = ProductionBom::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('approve', $bom);

        try {
            $this->bomService->approve($id, auth()->id());

            return $this->successResponse(
                new ProductionBomDetailResource($bom->fresh($this->detailRelations())),
                'BOM approved successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/boms/{id}/duplicate
     * Copy data for a new version (Duplicate with `version`, Revise with `version_type`) — nothing saved.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create', ProductionBom::class);

        $tenantId = $this->getTenantId();
        $bom = ProductionBom::where('tenant_id', $tenantId)->findOrFail($id);

        // An explicit version (Duplicate) wins over a bump (Revise)
        $explicitVersion = trim((string) $request->input('version', ''));
        $bumpType = $request->input('version_type', $request->input('bump_type', 'patch'));
        $newVersion = $explicitVersion !== '' ? $explicitVersion : match ($bumpType) {
            'major' => $this->versionService->incrementMajor($bom->version),
            'minor' => $this->versionService->incrementMinor($bom->version),
            default => $this->versionService->incrementPatch($bom->version),
        };

        while ($explicitVersion === '' && $this->bomRepository->versionExistsUnscoped($bom->product_id, $newVersion, $tenantId)) {
            $newVersion = match ($bumpType) {
                'major' => $this->versionService->incrementMajor($newVersion),
                'minor' => $this->versionService->incrementMinor($newVersion),
                default => $this->versionService->incrementPatch($newVersion),
            };
        }

        try {
            // BM-IMPL-03 #7 / BM-IMPL-19 #5: nothing is saved — the copy data comes back; the client sends it
            // (with source_bom_id) to the normal create endpoint to save the new version
            return $this->successResponse(
                $this->bomService->buildCopyPayload($id, $newVersion),
                'Copy data for the new version — not saved. Send it to the create endpoint to save.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/boms/{id}/clone
     * Alias for duplicate.
     */
    public function clone(Request $request, int $id): JsonResponse
    {
        return $this->duplicate($request, $id);
    }

    /**
     * POST /api/v1/production/boms/{id}/reject
     * Reject a submitted BOM.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $bom = ProductionBom::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('approve', $bom);

        $request->validate(['comments' => 'nullable|string|max:1000']);

        try {
            $this->bomService->reject($id, auth()->id(), $request->input('comments'));

            return $this->successResponse(
                new ProductionBomDetailResource($bom->fresh($this->detailRelations())),
                'BOM rejected successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/boms/{id}/cancel
     * Cancel an active or draft BOM.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $bom = ProductionBom::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('cancel', $bom);

        $request->validate(['comments' => 'nullable|string|max:1000']);

        try {
            $this->bomService->cancel($id, auth()->id(), $request->input('comments'));

            return $this->successResponse(
                new ProductionBomDetailResource($bom->fresh($this->detailRelations())),
                'BOM cancelled successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/boms/import
     * Import BOM master records from spreadsheet.
     */
    public function import(Request $request, \App\Domains\Production\Services\ProductionMasterImportService $importService): JsonResponse
    {
        Gate::authorize('create', ProductionBom::class);

        $request->validate([
            'file'     => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
            'strategy' => 'nullable|string|in:create,update',
        ]);

        $strategy = $request->input('strategy', 'create');

        try {
            $result = $importService->import('boms', $request->file('file'), $strategy, $this->getTenantId(), auth()->id());

            if ($result['status'] === 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Import validation failed. No records were imported.',
                    'data'    => $result,
                ], 422);
            }

            return $this->successResponse($result, 'BOMs imported successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/production/boms/export
     * Export BOM master records.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', ProductionBom::class);

        $tenantId = $this->getTenantId();
        $format = $request->query('format', 'xlsx');
        $fileName = 'boms_export.' . ($format === 'csv' ? 'csv' : 'xlsx');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BomExport($tenantId, $request->all()),
            $fileName
        );
    }
}
