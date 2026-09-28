<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Requests\StoreProductionScheduleRequest;
use App\Domains\Production\Resources\Api\ProductionScheduleResource;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulePreReleaseValidationService;
use App\Domains\Production\Services\SchedulingService;
use App\Exports\ProductionScheduleExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ProductionScheduleApiController
 *
 * REST API controller for Production Schedules, forward/backward capacity dispatch, and shop floor release.
 */
class ProductionScheduleApiController extends ApiBaseController
{
    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly SchedulePreReleaseValidationService $validationService,
        private readonly ProductionOrderService $orderService,
    ) {
    }

    /**
     * GET /api/v1/production/schedules
     * List production schedules with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductionSchedule::class);

        $tenantId = $this->getTenantId();

        $query = ProductionSchedule::where('tenant_id', $tenantId)
            ->with(['order.product']);

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('schedule_number', 'like', $search)
                  ->orWhereHas('order', function ($o) use ($search) {
                      $o->where('order_number', 'like', $search);
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('scheduling_type')) {
            $query->where('scheduling_type', $request->query('scheduling_type'));
        }

        if ($request->filled('production_order_id')) {
            $query->where('production_order_id', (int) $request->query('production_order_id'));
        }

        $perPage = $this->getPerPage($request);
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($paginator, ProductionScheduleResource::class, 'Production schedules retrieved successfully.');
    }

    /**
     * GET /api/v1/production/schedules/{id}
     * Get details of a production schedule including scheduled operations.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $schedule = ProductionSchedule::where('tenant_id', $tenantId)
            ->with(['order.product', 'operations.workCenter', 'operations.machine'])
            ->findOrFail($id);

        Gate::authorize('view', $schedule);

        return $this->successResponse(
            new ProductionScheduleResource($schedule),
            'Production schedule details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/schedules
     * Generate a new schedule for a released production order.
     */
    public function store(StoreProductionScheduleRequest $request): JsonResponse
    {
        Gate::authorize('create', ProductionSchedule::class);

        $tenantId = $this->getTenantId();
        $validated = $request->validated();

        $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($validated['production_order_id']);

        try {
            if ($order->isDraft()) {
                $this->orderService->release($order->id, auth()->id() ?? 1);
                $order->refresh();
            }

            $startDate = Carbon::parse($validated['start_date']);
            $schedule = $this->schedulingService->generateSchedule($order, $startDate, $validated['scheduling_type']);

            if (!empty($validated['notes'])) {
                $schedule->update(['notes' => $validated['notes']]);
            }

            return $this->createdResponse(
                new ProductionScheduleResource($schedule->load(['order.product', 'operations.workCenter', 'operations.machine'])),
                "Production schedule {$schedule->schedule_number} generated successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/schedules/{id}/release
     * Release a confirmed schedule to shop floor execution.
     */
    public function release(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $schedule = ProductionSchedule::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('release', $schedule);

        if (!$schedule->isScheduled()) {
            return $this->errorResponse('Only scheduled (confirmed) schedules can be released.', 422);
        }

        $validationResult = $this->validationService->validate($schedule);

        if (!$validationResult['can_release']) {
            $errorMsgs = collect($validationResult['errors'])->pluck('message')->implode(' ');
            return $this->errorResponse("Release blocked: {$errorMsgs}", 422);
        }

        try {
            $schedule->update([
                'status'       => ProductionSchedule::STATUS_RELEASED,
                'released_at'  => now(),
                'released_by'  => auth()->id(),
            ]);

            app(\App\Domains\Production\Services\ProductionNotificationService::class)->notifyScheduleReleased($schedule);

            return $this->successResponse(
                new ProductionScheduleResource($schedule->fresh(['order.product', 'operations.workCenter', 'operations.machine'])),
                "Schedule [{$schedule->schedule_number}] released to shop floor successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/production/schedules/{id}/cancel
     * Cancel an active production schedule.
     */
    public function cancel(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $schedule = ProductionSchedule::where('tenant_id', $tenantId)->findOrFail($id);

        Gate::authorize('cancel', $schedule);

        if ($schedule->isFrozen()) {
            return $this->errorResponse('Schedule is already in a terminal state and cannot be cancelled.', 422);
        }

        try {
            $schedule->update([
                'status'       => ProductionSchedule::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);

            return $this->successResponse(
                new ProductionScheduleResource($schedule->fresh(['order.product', 'operations'])),
                "Schedule [{$schedule->schedule_number}] cancelled successfully."
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/production/schedules/export
     * Export production schedules.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', ProductionSchedule::class);

        $tenantId = $this->getTenantId();
        $format = $request->query('format', 'xlsx');
        $fileName = 'production_schedules_export.' . ($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new ProductionScheduleExport($tenantId, $request->all()),
            $fileName
        );
    }
}
