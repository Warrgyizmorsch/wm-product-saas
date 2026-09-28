<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Repositories\ProductionWipRepositoryInterface;
use App\Domains\Production\Resources\Api\ProductionWipResource;
use App\Domains\Production\Services\ProductionWipService;
use App\Exports\ProductionWipExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ProductionWipApiController
 *
 * REST API controller for Work-In-Progress (WIP) operational tracking.
 * Excludes monetary valuation per domain constraints.
 */
class ProductionWipApiController extends ApiBaseController
{
    public function __construct(
        private readonly ProductionWipService $wipService,
        private readonly ProductionWipRepositoryInterface $wipRepository,
    ) {
    }

    /**
     * GET /api/v1/production/wip
     * List WIP records with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $tenantId = $this->getTenantId();

        $query = ProductionWip::where('tenant_id', $tenantId)
            ->with(['order:id,order_number', 'product:id,name,sku', 'currentWorkCenter:id,name,code', 'currentMachine:id,name,code']);

        if ($request->filled('production_order_id')) {
            $query->where('production_order_id', (int) $request->query('production_order_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('work_center_id')) {
            $query->where('current_work_center_id', (int) $request->query('work_center_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = $this->getPerPage($request);
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($paginator, ProductionWipResource::class, 'WIP records retrieved successfully.');
    }

    /**
     * GET /api/v1/production/wip/{id}
     * Get a single WIP record.
     */
    public function show(int $id): JsonResponse
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $tenantId = $this->getTenantId();

        $wip = ProductionWip::where('tenant_id', $tenantId)
            ->with(['order:id,order_number', 'product:id,name,sku', 'currentWorkCenter:id,name,code', 'currentMachine:id,name,code'])
            ->findOrFail($id);

        return $this->successResponse(
            new ProductionWipResource($wip),
            'WIP details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/wip/{id}/transfer
     * Transfer WIP quantity to next operational stage.
     */
    public function transfer(Request $request, int $id): JsonResponse
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $tenantId = $this->getTenantId();
        $wip = ProductionWip::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'from_operation_id' => 'required|integer',
            'to_operation_id'   => 'required|integer',
            'quantity'          => 'required|numeric|min:0.0001',
            'remarks'           => 'nullable|string|max:255',
        ]);

        $toOpId = (int) $validated['to_operation_id'];

        $orderOpExists = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $wip->production_order_id)
            ->where(function ($q) use ($toOpId) {
                $q->where('id', $toOpId)->orWhere('routing_operation_id', $toOpId);
            })
            ->exists();

        if (!$orderOpExists) {
            return $this->errorResponse('Destination operation does not belong to this Production Order.', 422);
        }

        try {
            $this->wipService->transferWip(
                $id,
                (int) $validated['from_operation_id'],
                $toOpId,
                (float) $validated['quantity'],
                $validated['remarks'] ?? null,
                auth()->id()
            );

            return $this->successResponse(
                new ProductionWipResource($wip->fresh(['order', 'product', 'currentWorkCenter', 'currentMachine'])),
                'WIP quantity transferred to next stage successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/wip/{id}/convert
     * Convert WIP to finished goods inventory.
     */
    public function convert(Request $request, int $id): JsonResponse
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $tenantId = $this->getTenantId();
        $wip = ProductionWip::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'warehouse_id'   => 'required|exists:warehouses,id',
            'quality_status' => 'nullable|string|in:passed,quarantine,failed',
            'remarks'        => 'nullable|string|max:255',
        ]);

        try {
            $this->wipService->convertWipToFinishedGoods(
                $id,
                (int) $validated['warehouse_id'],
                $validated['remarks'] ?? null,
                auth()->id(),
                $validated['quality_status'] ?? 'passed'
            );

            return $this->successResponse(
                new ProductionWipResource($wip->fresh(['order', 'product', 'currentWorkCenter', 'currentMachine'])),
                'WIP converted and Finished Goods stock received successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/production/wip/export
     * Export WIP records.
     */
    public function export(Request $request)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $tenantId = $this->getTenantId();
        $format = $request->query('format', 'xlsx');
        $fileName = 'production_wip_export.' . ($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new ProductionWipExport($tenantId, $request->all()),
            $fileName
        );
    }
}
