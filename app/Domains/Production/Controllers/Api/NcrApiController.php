<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Requests\NcrDispositionRequest;
use App\Domains\Production\Requests\StoreNcrRequest;
use App\Domains\Production\Resources\Api\NcrResource;
use App\Domains\Production\Services\NcrService;
use App\Domains\Production\Services\ScrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * NcrApiController
 *
 * REST API controller for Non-Conformance Reports (NCR) and Quality Scrap Disposals.
 */
class NcrApiController extends ApiBaseController
{
    public function __construct(
        private readonly NcrService $ncrService,
        private readonly ScrapService $scrapService,
    ) {
    }

    /**
     * GET /api/v1/production/quality/ncrs
     * List NCRs with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view', ProductionNcr::class);

        $tenantId = $this->getTenantId();

        $query = ProductionNcr::where('tenant_id', $tenantId)
            ->with(['order:id,order_number']);

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('ncr_number', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('production_order_id')) {
            $query->where('production_order_id', (int) $request->query('production_order_id'));
        }

        $perPage = $this->getPerPage($request);
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($paginator, NcrResource::class, 'NCR records retrieved successfully.');
    }

    /**
     * GET /api/v1/production/quality/ncrs/{id}
     * Show a single NCR.
     */
    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', ProductionNcr::class);

        $tenantId = $this->getTenantId();

        $ncr = ProductionNcr::where('tenant_id', $tenantId)
            ->with(['order:id,order_number'])
            ->findOrFail($id);

        return $this->successResponse(
            new NcrResource($ncr),
            'NCR details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/quality/ncrs
     * Create a new NCR.
     */
    public function store(StoreNcrRequest $request): JsonResponse
    {
        Gate::authorize('manage', ProductionNcr::class);

        $tenantId = $this->getTenantId();

        try {
            $ncr = $this->ncrService->createNcr($tenantId, $request->validated());

            return $this->createdResponse(
                new NcrResource($ncr->load('order:id,order_number')),
                "Non-conformance report {$ncr->ncr_number} logged successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/quality/ncrs/{id}/disposition
     * Register disposition for an NCR.
     */
    public function disposition(NcrDispositionRequest $request, int $id): JsonResponse
    {
        Gate::authorize('manage', ProductionNcr::class);

        $tenantId = $this->getTenantId();
        $ncr = ProductionNcr::where('tenant_id', $tenantId)->findOrFail($id);

        $type = $request->input('disposition_type');
        $data = $request->only([
            'original_production_order_id',
            'cost_estimate',
            'work_center_id',
            'category',
            'reason_code',
            'quantity',
            'cost',
        ]);

        try {
            $this->ncrService->processDisposition($id, $type, $data, $tenantId);

            return $this->successResponse(
                new NcrResource($ncr->fresh(['order:id,order_number'])),
                'NCR disposition processed successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/quality/ncrs/{id}/close
     * Close an NCR.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        Gate::authorize('approve', ProductionNcr::class);

        $tenantId = $this->getTenantId();
        $ncr = ProductionNcr::where('tenant_id', $tenantId)->findOrFail($id);

        $signature = $request->input('esignature') ?: 'NCR-CLOSE-API';

        try {
            $this->ncrService->closeNcr($id, auth()->id(), $signature, $tenantId);

            return $this->successResponse(
                new NcrResource($ncr->fresh(['order:id,order_number'])),
                'NCR closed and verified successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/quality/scrap/{id}/approve
     * Approve scrap disposal.
     */
    public function approveScrap(int $id): JsonResponse
    {
        Gate::authorize('approve', ProductionScrapDisposal::class);

        $tenantId = $this->getTenantId();
        $scrap = ProductionScrapDisposal::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            $this->scrapService->approveDisposal($id, auth()->id(), $tenantId);

            return $this->successResponse(null, 'Scrap disposal approved successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
