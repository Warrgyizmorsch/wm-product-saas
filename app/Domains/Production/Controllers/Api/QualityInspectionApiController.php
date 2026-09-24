<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Repositories\ProductionQualityRepositoryInterface;
use App\Domains\Production\Requests\Api\QualityInspectionResultsApiRequest;
use App\Domains\Production\Requests\Api\StoreQualityInspectionApiRequest;
use App\Domains\Production\Resources\Api\QualityInspectionResource;
use App\Domains\Production\Services\QualityInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QualityInspectionApiController
 *
 * REST API controller for Quality Control inspections and results recording.
 */
class QualityInspectionApiController extends ApiBaseController
{
    public function __construct(
        private readonly ProductionQualityRepositoryInterface $qualityRepository,
        private readonly QualityInspectionService $inspectionService,
    ) {
    }

    /**
     * GET /api/v1/production/quality/inspections
     * List quality inspections (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view', ProductionQualityInspection::class);

        $filters = $request->only(['search', 'status', 'result']);
        $perPage = $this->getPerPage($request);
        $paginator = $this->qualityRepository->paginateInspections($filters, $perPage);

        return $this->paginatedResponse($paginator, QualityInspectionResource::class, 'Quality inspections retrieved successfully.');
    }

    /**
     * GET /api/v1/production/quality/inspections/{id}
     * Show quality inspection details.
     */
    public function show(int $id): JsonResponse
    {
        $this->authorize('view', ProductionQualityInspection::class);

        $tenantId = $this->getTenantId();
        $inspection = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->with(['order:id,order_number', 'operation:id,operation_name'])
            ->findOrFail($id);

        return $this->successResponse(
            new QualityInspectionResource($inspection),
            'Quality inspection details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/quality/inspections
     * Create a new inspection checklist.
     */
    public function store(StoreQualityInspectionApiRequest $request): JsonResponse
    {
        $this->authorize('manage', ProductionQualityInspection::class);

        $tenantId = $this->getTenantId();

        try {
            $inspection = $this->inspectionService->createInspection($tenantId, $request->validated());

            return $this->createdResponse(
                new QualityInspectionResource($inspection),
                'Quality inspection created successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/quality/inspections/{inspection}/submit
     * Submit criteria inspection results.
     */
    public function submitResults(QualityInspectionResultsApiRequest $request, int $inspection): JsonResponse
    {
        return $this->saveResults($request, $inspection);
    }

    /**
     * POST /api/v1/production/quality/inspections/{id}/results
     * Record criteria inspection results.
     */
    public function saveResults(QualityInspectionResultsApiRequest $request, int $id): JsonResponse
    {
        $this->authorize('manage', ProductionQualityInspection::class);

        $tenantId = $this->getTenantId();
        ProductionQualityInspection::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            $this->inspectionService->recordResults($id, $request->validated('results'), $tenantId);

            return $this->successResponse(null, 'Inspection results recorded successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/quality/inspections/{id}/approve
     * Approve quality inspection.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $this->authorize('approve', ProductionQualityInspection::class);

        $tenantId = $this->getTenantId();
        ProductionQualityInspection::where('tenant_id', $tenantId)->findOrFail($id);

        $signature = $request->input('esignature') ?: 'API_SIGNED';

        try {
            $this->inspectionService->approveInspection($id, auth()->id(), $signature, $tenantId);

            return $this->successResponse(null, 'Inspection approved and audited successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/quality/orders/{order}/quick-check
     * Fast operator inline quality check from shopfloor tablet.
     */
    public function quickCheck(Request $request, ?int $order = null): JsonResponse
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->role === 'admin'
                || $user->role === 'super_admin'
                || $user->hasProductionPermission('production.quality.manage')
                || $user->hasProductionPermission('production.mes.execute')
                || $user->hasProductionPermission('production.order.update')
                || $user->can('manage', ProductionQualityInspection::class)
            ),
            403,
            'Unauthorized to perform inline quality inspection.'
        );

        $tenantId = $this->getTenantId();

        $targetOrderId = $order ?: $request->input('production_order_id');
        if ($targetOrderId) {
            \App\Domains\Production\Models\ProductionOrder::where('tenant_id', $tenantId)->findOrFail((int) $targetOrderId);
        }

        $validated = $request->validate([
            'production_order_operation_id' => 'nullable|integer|exists:production_order_operations,id',
            'production_order_id'           => 'nullable|integer|exists:production_orders,id',
            'batch_id'                      => 'nullable|integer',
            'result'                        => 'required|in:passed,hold,failed',
            'remarks'                       => 'nullable|string|max:500',
            'stage'                         => 'nullable|string|max:50',
        ]);

        if ($order && empty($validated['production_order_id'])) {
            $validated['production_order_id'] = $order;
        }

        if (!empty($validated['production_order_operation_id'])) {
            \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
                ->findOrFail((int) $validated['production_order_operation_id']);
        }

        try {
            $inspection = $this->inspectionService->quickOperatorInspection($tenantId, $validated, auth()->id());

            return $this->createdResponse(
                new QualityInspectionResource($inspection->fresh(['order'])),
                'Operator quick check recorded successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
