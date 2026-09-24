<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Requests\Api\StartDowntimeApiRequest;
use App\Domains\Production\Resources\Api\MesOperationResource;
use App\Domains\Production\Services\DowntimeService;
use App\Domains\Production\Services\MesExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MesExecutionApiController
 *
 * REST API for Shop Floor Execution (MES), dispatch queue, and machine downtime tracking.
 */
class MesExecutionApiController extends ApiBaseController
{
    public function __construct(
        private readonly MesExecutionService $mesService,
        private readonly DowntimeService $downtimeService,
    ) {
    }

    /**
     * GET /api/v1/production/mes/queue
     * Alias for operations dispatch queue.
     */
    public function operatorQueue(Request $request): JsonResponse
    {
        return $this->operations($request);
    }

    /**
     * GET /api/v1/production/mes/operations
     * List operations queue for shop floor / operator tablets.
     */
    public function operations(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();

        $query = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->with(['productionOrder.product', 'workCenter:id,name,code', 'machine:id,name,code,current_state']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            // Default to actionable operations
            $query->whereIn('status', [
                ProductionOrderOperation::STATUS_READY,
                ProductionOrderOperation::STATUS_RUNNING,
                ProductionOrderOperation::STATUS_PAUSED,
                ProductionOrderOperation::STATUS_WAITING,
            ]);
        }

        if ($request->filled('work_center_id')) {
            $query->where('work_center_id', (int) $request->query('work_center_id'));
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_id', (int) $request->query('machine_id'));
        }

        if ($request->filled('production_order_id')) {
            $query->where('production_order_id', (int) $request->query('production_order_id'));
        }

        $perPage = $this->getPerPage($request);
        $paginator = $query->orderBy('sequence')->paginate($perPage);

        return $this->paginatedResponse($paginator, MesOperationResource::class, 'MES operations queue retrieved successfully.');
    }

    /**
     * GET /api/v1/production/mes/operations/{operation}
     * Get execution context for a specific operation.
     */
    public function show(int $operation): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        $record = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->with(['order.product', 'workCenter', 'machine'])
            ->findOrFail($operation);

        return $this->successResponse(
            new MesOperationResource($record),
            'Operation execution details retrieved successfully.'
        );
    }

    /**
     * Resolve or bridge a ProductionScheduleOperation ID from given operation ID.
     */
    private function resolveScheduleOperationId(int $operationId, int $tenantId): int
    {
        $schedOp = \App\Domains\Production\Models\ProductionScheduleOperation::where('tenant_id', $tenantId)->find($operationId);
        if ($schedOp) {
            return $schedOp->id;
        }

        $schedOp = \App\Domains\Production\Models\ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('production_order_operation_id', $operationId)
            ->first();

        if ($schedOp) {
            return $schedOp->id;
        }

        $orderOp = ProductionOrderOperation::where('tenant_id', $tenantId)->findOrFail($operationId);

        $schedule = \App\Domains\Production\Models\ProductionSchedule::firstOrCreate(
            ['tenant_id' => $tenantId, 'production_order_id' => $orderOp->production_order_id],
            [
                'schedule_number' => 'SCH-' . uniqid(),
                'status' => 'confirmed',
                'start_date' => now(),
                'end_date' => now()->addDays(7),
                'created_by' => auth()->id() ?: 1,
            ]
        );

        $newSchedOp = \App\Domains\Production\Models\ProductionScheduleOperation::create([
            'tenant_id' => $tenantId,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $orderOp->production_order_id,
            'production_order_operation_id' => $orderOp->id,
            'sequence' => $orderOp->sequence ?? 1,
            'operation_number' => $orderOp->operation_number ?? 'OP-01',
            'name' => $orderOp->name ?? 'Operation',
            'work_center_id' => $orderOp->work_center_id,
            'machine_id' => $orderOp->machine_id,
            'status' => \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_READY,
            'planned_start' => now(),
            'planned_finish' => now()->addHour(),
            'planned_duration_minutes' => 60,
        ]);

        return $newSchedOp->id;
    }

    /**
     * POST /api/v1/production/mes/operations/{operation}/start
     * Start operation execution on the shop floor.
     */
    public function start(Request $request, int $operation): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        $scheduleOpId = $this->resolveScheduleOperationId($operation, $tenantId);

        $request->validate([
            'machine_id' => 'nullable|integer|exists:production_machines,id',
        ]);

        try {
            $this->mesService->startOperation(
                $scheduleOpId,
                $request->filled('machine_id') ? (int) $request->input('machine_id') : null,
                auth()->id()
            );

            return $this->successResponse(null, 'Operation started successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/mes/operations/{operation}/pause
     * Pause an ongoing operation.
     */
    public function pause(Request $request, int $operation): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        $scheduleOpId = $this->resolveScheduleOperationId($operation, $tenantId);

        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $this->mesService->pauseOperation($scheduleOpId, $request->input('remarks'), auth()->id());

            return $this->successResponse(null, 'Operation paused.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/mes/operations/{operation}/resume
     * Resume a paused operation.
     */
    public function resume(int $operation): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        $scheduleOpId = $this->resolveScheduleOperationId($operation, $tenantId);

        try {
            $this->mesService->resumeOperation($scheduleOpId, auth()->id());

            return $this->successResponse(null, 'Operation resumed.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/mes/operations/{operation}/complete
     * Complete operation and log output quantities.
     */
    public function complete(Request $request, int $operation): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        $scheduleOpId = $this->resolveScheduleOperationId($operation, $tenantId);

        $request->validate([
            'quantity_produced' => 'required|numeric|min:0',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'quantity_scrapped' => 'nullable|numeric|min:0',
            'remarks'           => 'nullable|string|max:500',
        ]);

        try {
            $this->mesService->completeOperation($scheduleOpId, $request->all(), auth()->id());

            return $this->successResponse(null, 'Operation completed successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    public function startOperation(Request $request, int $operation): JsonResponse
    {
        return $this->start($request, $operation);
    }

    public function pauseOperation(Request $request, int $operation): JsonResponse
    {
        return $this->pause($request, $operation);
    }

    public function resumeOperation(int $operation): JsonResponse
    {
        return $this->resume($operation);
    }

    public function completeOperation(Request $request, int $operation): JsonResponse
    {
        return $this->complete($request, $operation);
    }

    /**
     * POST /api/v1/production/mes/operations/{operation}/log-progress
     * Log partial progress on an operation.
     */
    public function logProgress(Request $request, int $operation): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        ProductionOrderOperation::where('tenant_id', $tenantId)->findOrFail($operation);

        $request->validate([
            'quantity_produced' => 'required|numeric|min:0',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'quantity_scrapped' => 'nullable|numeric|min:0',
            'remarks'           => 'nullable|string|max:500',
        ]);

        try {
            $this->mesService->logPartialProgress($operation, $request->all(), auth()->id());

            return $this->successResponse(null, 'Progress logged successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/mes/operations/{op}/scrap
     * Log scrap directly from the shop floor.
     */
    public function scrap(Request $request, int $op): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        ProductionOrderOperation::where('tenant_id', $tenantId)->findOrFail($op);

        $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'reason'   => 'required|string|max:255',
        ]);

        try {
            $this->mesService->recordOperationalScrap(
                $op,
                (float) $request->input('quantity'),
                $request->input('reason'),
                auth()->id(),
                $request->filled('product_id') ? (int) $request->input('product_id') : null,
                $request->filled('batch_id') ? (int) $request->input('batch_id') : null
            );

            return $this->successResponse(null, 'Operational scrap recorded successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/mes/downtime/start
     * Start recording machine downtime.
     */
    public function startDowntime(StartDowntimeApiRequest $request): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();

        try {
            $this->downtimeService->startDowntime(
                $tenantId,
                (int) $request->validated('machine_id'),
                $request->validated('category'),
                $request->validated('reason'),
                auth()->id(),
                $request->only(['production_order_id', 'production_order_operation_id', 'remarks'])
            );

            return $this->createdResponse(null, 'Downtime tracking started.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/mes/downtime/{id}/end
     * Conclude an active machine downtime event.
     */
    public function endDowntime(Request $request, int $id): JsonResponse
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();

        $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $this->downtimeService->endDowntime(
                $tenantId,
                $id,
                auth()->id(),
                $request->input('remarks')
            );

            return $this->successResponse(null, 'Downtime event concluded.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
