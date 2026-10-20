<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\DTO\WorkCenterSectionDTO;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use App\Domains\Production\Repositories\WorkCenterSectionRepositoryInterface;
use App\Domains\Production\Requests\StoreWorkCenterSectionRequest;
use App\Domains\Production\Requests\UpdateWorkCenterSectionRequest;
use App\Domains\Production\Services\WorkCenterSectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkCenterSectionController extends Controller
{
    public function __construct(
        private readonly WorkCenterSectionRepositoryInterface $repository,
        private readonly WorkCenterSectionService $service,
        private readonly WorkCenterRepositoryInterface $workCenterRepository,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', WorkCenterSection::class);

        $filters  = $request->only(['work_center_id', 'status', 'search']);
        $sections = $this->repository->paginateAll($filters, 15)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($sections);
        }

        $workCenters = $this->workCenterRepository->getActiveWorkCenters();

        return view('modules.production.work-center-sections.index', compact('sections', 'workCenters', 'filters'));
    }

    public function byWorkCenter(int $workCenterId): JsonResponse
    {
        Gate::authorize('viewAny', WorkCenterSection::class);

        $tenantId = require_tenant_id();
        $workCenter = $this->workCenterRepository->find($workCenterId);
        abort_if(!$workCenter || $workCenter->tenant_id !== $tenantId, 404, 'Work center not found.');

        $sections = $this->repository->getByWorkCenter($workCenterId, true);

        return response()->json($sections->map(fn (WorkCenterSection $s) => [
            'id'                         => $s->id,
            'work_center_id'             => $s->work_center_id,
            'name'                       => $s->name,
            'code'                       => $s->code,
            'workers_required'           => $s->workers_required,
            'supports_manual_production' => (bool) $s->supports_manual_production,
            'manual_capacity_per_hour'   => $s->manual_capacity_per_hour !== null ? (float) $s->manual_capacity_per_hour : null,
            'status'                     => $s->status,
            'label'                      => "{$s->name} ({$s->code})",
            'shifts'                     => $s->shifts->map(fn ($sh) => [
                'id'               => $sh->id,
                'name'             => $sh->name,
                'code'             => $sh->code,
                'workers_required' => (int) ($sh->pivot->workers_required ?? $s->workers_required ?? 1),
            ]),
        ])->values());
    }

    public function store(StoreWorkCenterSectionRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', WorkCenterSection::class);

        try {
            $tenantId = require_tenant_id();
            $dto      = WorkCenterSectionDTO::fromArray($request->validated());
            $section  = $this->service->create($dto, $tenantId);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Section '{$section->name}' created successfully.",
                    'data'    => $section,
                ], 201);
            }

            return redirect()
                ->route('production.work-centers.show', $section->work_center_id)
                ->with('success', "Section '{$section->name}' created successfully.");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View|JsonResponse
    {
        $section = $this->repository->find($id);
        abort_if(!$section, 404, 'Section not found.');

        Gate::authorize('view', $section);

        if (request()->wantsJson()) {
            return response()->json($section);
        }

        return view('modules.production.work-center-sections.show', compact('section'));
    }

    public function update(UpdateWorkCenterSectionRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $section = $this->repository->find($id);
        abort_if(!$section, 404, 'Section not found.');

        Gate::authorize('update', $section);

        try {
            $dto     = WorkCenterSectionDTO::fromArray($request->validated());
            $updated = $this->service->update($id, $dto);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Section '{$updated->name}' updated successfully.",
                    'data'    => $updated,
                ]);
            }

            return redirect()
                ->route('production.work-centers.show', $updated->work_center_id)
                ->with('success', "Section '{$updated->name}' updated successfully.");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        $section = $this->repository->find($id);
        abort_if(!$section, 404, 'Section not found.');

        Gate::authorize('delete', $section);

        try {
            $workCenterId = $section->work_center_id;
            $this->service->delete($id);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Section deleted successfully.',
                ]);
            }

            return redirect()
                ->route('production.work-centers.show', $workCenterId)
                ->with('success', 'Section deleted successfully.');
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
