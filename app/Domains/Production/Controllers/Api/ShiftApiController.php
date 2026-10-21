<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use App\Domains\Production\Requests\StoreShiftRequest;
use App\Domains\Production\Resources\Api\ProductionShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ShiftApiController
 *
 * REST API controller for Production Shifts.
 * Directly leverages WorkCenterRepositoryInterface per architectural guidelines.
 */
class ShiftApiController extends ApiBaseController
{
    public function __construct(
        private readonly WorkCenterRepositoryInterface $workCenterRepository,
    ) {
    }

    /**
     * GET /api/v1/production/shifts
     * List production shifts.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $filters = $request->only(['search', 'active']);
        $perPage = $this->getPerPage($request);

        $shifts = $this->workCenterRepository->paginateShifts($filters, $perPage);

        return $this->paginatedResponse($shifts, ProductionShiftResource::class, 'Production shifts retrieved successfully.');
    }

    /**
     * GET /api/v1/production/shifts/{id}
     * Get a single production shift.
     */
    public function show(int $id): JsonResponse
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $shift = $this->workCenterRepository->findShift($id);

        if (!$shift) {
            return $this->errorResponse('Production shift not found.', 404);
        }

        return $this->successResponse(
            new ProductionShiftResource($shift),
            'Production shift details retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/production/shifts
     * Create a new production shift.
     */
    public function store(StoreShiftRequest $request): JsonResponse
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = $this->getTenantId();
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['overtime_allowed'] = $request->boolean('overtime_allowed');
        $data['active'] = $request->boolean('active', true);

        try {
            $shift = $this->workCenterRepository->createShift($data);

            return $this->createdResponse(
                new ProductionShiftResource($shift),
                'Production shift created successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/production/shifts/{id}
     * Update an existing production shift.
     */
    public function update(StoreShiftRequest $request, int $id): JsonResponse
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $shift = $this->workCenterRepository->findShift($id);

        if (!$shift) {
            return $this->errorResponse('Production shift not found.', 404);
        }

        $data = $request->validated();
        $data['overtime_allowed'] = $request->boolean('overtime_allowed');
        $data['active'] = $request->boolean('active');

        try {
            $updated = $this->workCenterRepository->updateShift($id, $data);

            return $this->successResponse(
                new ProductionShiftResource($updated),
                'Production shift updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * DELETE /api/v1/production/shifts/{id}
     * Delete a production shift.
     */
    public function destroy(int $id): JsonResponse
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $shift = $this->workCenterRepository->findShift($id);

        if (!$shift) {
            return $this->errorResponse('Production shift not found.', 404);
        }

        try {
            $this->workCenterRepository->deleteShift($id);

            return $this->successResponse(null, 'Production shift deleted successfully.');
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
