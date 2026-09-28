<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use App\Domains\Production\Resources\Api\MaintenanceWorkOrderResource;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * MaintenanceWorkOrderApiController
 *
 * REST API controller for Plant Maintenance Work Orders and Machine Breakdown reporting.
 */
class MaintenanceWorkOrderApiController extends ApiBaseController
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository,
        private readonly MaintenanceWorkOrderService $service,
    ) {
    }

    /**
     * GET /api/v1/production/maintenance/work-orders
     * List maintenance work orders.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Machine::class);

        $tenantId = $this->getTenantId();
        $filters = $request->only(['machine_id', 'status', 'type', 'search']);
        $perPage = $this->getPerPage($request);

        $workOrders = $this->repository->paginateWorkOrders($tenantId, $filters, $perPage);

        return $this->paginatedResponse($workOrders, MaintenanceWorkOrderResource::class, 'Maintenance work orders retrieved successfully.');
    }

    /**
     * GET /api/v1/production/maintenance/work-orders/{id}
     * Get a single maintenance work order.
     */
    public function show(int $id): JsonResponse
    {
        Gate::authorize('viewAny', Machine::class);

        $tenantId = $this->getTenantId();
        $workOrder = $this->repository->findWorkOrder($id, $tenantId);

        if (!$workOrder) {
            return $this->errorResponse('Maintenance work order not found.', 404);
        }

        $workOrder->load(['machine:id,name,code,current_state', 'technician:id,name']);

        return $this->successResponse(
            new MaintenanceWorkOrderResource($workOrder),
            'Maintenance work order details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/maintenance/work-orders
     * Create a preventive or calibration maintenance work order.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Machine::class);

        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'machine_id'             => ['required', 'integer'],
            'type'                   => ['required', 'in:preventive,breakdown,calibration'],
            'priority'               => ['required', 'in:low,medium,high,critical'],
            'assigned_technician_id' => ['nullable', 'integer'],
            'planned_start'          => ['nullable', 'date'],
            'planned_end'            => ['nullable', 'date'],
            'problem_description'    => ['required', 'string', 'max:2000'],
        ]);

        try {
            $wo = $this->service->createWorkOrder($tenantId, $validated, auth()->id());

            return $this->createdResponse(
                new MaintenanceWorkOrderResource($wo->load(['machine:id,name,code,current_state', 'technician:id,name'])),
                "Maintenance work order {$wo->work_order_number} created successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/maintenance/work-orders/breakdown
     * Report an urgent machine breakdown and generate an emergency work order.
     */
    public function reportBreakdown(Request $request): JsonResponse
    {
        Gate::authorize('create', Machine::class);

        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'machine_id' => ['required', 'integer'],
            'reason'     => ['required', 'string', 'max:2000'],
            'priority'   => ['required', 'in:low,medium,high,critical'],
        ]);

        try {
            $wo = $this->service->reportBreakdown(
                $tenantId,
                (int) $validated['machine_id'],
                $validated['reason'],
                auth()->id(),
                $validated['priority']
            );

            return $this->createdResponse(
                new MaintenanceWorkOrderResource($wo->load(['machine:id,name,code,current_state', 'technician:id,name'])),
                "Machine breakdown reported. Work order {$wo->work_order_number} created and machine placed under maintenance."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/maintenance/work-orders/{id}/complete
     * Complete a maintenance work order and restore machine state.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create', Machine::class);

        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'work_performed' => ['required', 'string', 'max:2000'],
            'labor_hours'    => ['required', 'numeric', 'min:0.1'],
        ]);

        try {
            $wo = $this->service->completeWorkOrder(
                $id,
                $tenantId,
                auth()->id(),
                $validated['work_performed'],
                (float) $validated['labor_hours']
            );

            return $this->successResponse(
                new MaintenanceWorkOrderResource($wo->load(['machine:id,name,code,current_state', 'technician:id,name'])),
                "Maintenance work order {$wo->work_order_number} completed successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/maintenance/work-orders/{id}/cancel
     * Cancel a maintenance work order.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create', Machine::class);

        $tenantId = $this->getTenantId();
        $reason = $request->input('reason', 'Cancelled via API');

        try {
            $wo = $this->service->cancelWorkOrder($id, $tenantId, auth()->id(), $reason);

            return $this->successResponse(
                new MaintenanceWorkOrderResource($wo->load(['machine:id,name,code,current_state', 'technician:id,name'])),
                "Maintenance work order {$wo->work_order_number} cancelled successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
