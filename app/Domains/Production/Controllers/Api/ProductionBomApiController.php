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
     * GET /api/v1/production/boms/{id}
     * Get detailed BOM including component lines.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $bom = ProductionBom::where('tenant_id', $tenantId)
            ->with(['product.uom', 'baseUom', 'routing', 'items.material.uom', 'items.product'])
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
            $bom = $this->bomService->create($dto, auth()->id() ?: 1);

            return $this->createdResponse(
                new ProductionBomDetailResource($bom->load(['product', 'routing', 'items.material.uom', 'items.product'])),
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
        $bom = ProductionBom::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('update', $bom);

        if (!$bom->isDraft() && !$bom->isUnderRevision()) {
            return $this->errorResponse('Approved BOMs cannot be edited directly.', 409);
        }

        try {
            $existingItems = [];
            if (!$request->has('items')) {
                $existingItems = $bom->items->map(function ($item) {
                    return [
                        'material_id' => $item->material_id,
                        'quantity' => $item->quantity,
                        'uom_id' => $item->uom_id,
                        'scrap_percentage' => $item->scrap_percentage,
                        'sequence' => $item->sequence,
                        'is_critical' => $item->is_critical,
                        'child_bom_id' => $item->child_bom_id,
                        'notes' => $item->notes,
                    ];
                })->all();
            }

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
                new ProductionBomDetailResource($bom->fresh(['product', 'items.product'])),
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
                new ProductionBomDetailResource($bom->fresh(['product'])),
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
            $this->bomService->approve($id, auth()->id() ?: 1);

            return $this->successResponse(
                new ProductionBomDetailResource($bom->fresh(['product'])),
                'BOM approved successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/boms/{id}/duplicate
     * Duplicate a BOM to create a new revision.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create', ProductionBom::class);

        $tenantId = $this->getTenantId();
        $bom = ProductionBom::where('tenant_id', $tenantId)->findOrFail($id);

        $bumpType = $request->input('version_type', $request->input('bump_type', 'patch'));
        $newVersion = match ($bumpType) {
            'major' => $this->versionService->incrementMajor($bom->version),
            'minor' => $this->versionService->incrementMinor($bom->version),
            default => $this->versionService->incrementPatch($bom->version),
        };

        while ($this->bomRepository->versionExistsUnscoped($bom->product_id, $newVersion, $tenantId)) {
            $newVersion = match ($bumpType) {
                'major' => $this->versionService->incrementMajor($newVersion),
                'minor' => $this->versionService->incrementMinor($newVersion),
                default => $this->versionService->incrementPatch($newVersion),
            };
        }

        try {
            $newBom = $this->bomService->duplicateVersion($id, $newVersion, auth()->id() ?: 1);

            return $this->createdResponse(
                new ProductionBomDetailResource($newBom->load(['product', 'items.product'])),
                "New BOM version {$newBom->version} created as draft."
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
}
