<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\DTO\WorkCenterDTO;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use App\Domains\Production\Requests\StoreWorkCenterRequest;
use App\Domains\Production\Requests\UpdateWorkCenterRequest;
use App\Domains\Production\Services\WorkCenterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkCenterController extends Controller
{
    public function __construct(
        private readonly WorkCenterRepositoryInterface $repository,
        private readonly WorkCenterService $service
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkCenter::class);

        $filters     = $request->only(['status', 'work_center_type', 'search']);
        $workCenters = $this->repository->getAll($filters);

        $workCenterTypes = config('production.work_center_types', []);

        return view('modules.production.work-centers.index', compact('workCenters', 'filters', 'workCenterTypes'));
    }

    public function create(): View
    {
        Gate::authorize('create', WorkCenter::class);

        $workCenterTypes = config('production.work_center_types', []);
        $parentOptions = WorkCenter::orderBy('name')->get();
        return view('modules.production.work-centers.create', compact('workCenterTypes', 'parentOptions'));
    }

    public function store(StoreWorkCenterRequest $request): RedirectResponse
    {
        Gate::authorize('create', WorkCenter::class);

        try {
            $tenantId = require_tenant_id();
            $dto      = WorkCenterDTO::fromArray($request->validated());
            $wc       = $this->service->create($dto, $tenantId);

            return redirect()
                ->route('production.work-centers.show', $wc->id)
                ->with('success', "Work center '{$wc->name}' created successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('view', $workCenter);

        return view('modules.production.work-centers.show', compact('workCenter'));
    }

    public function edit(int $id): View
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('update', $workCenter);

        $workCenterTypes = config('production.work_center_types', []);
        $parentOptions = WorkCenter::where('id', '!=', $id)->orderBy('name')->get();
        return view('modules.production.work-centers.edit', compact('workCenter', 'workCenterTypes', 'parentOptions'));
    }

    public function update(UpdateWorkCenterRequest $request, int $id): RedirectResponse
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('update', $workCenter);

        try {
            $dto = WorkCenterDTO::fromArray($request->validated());
            $wc  = $this->service->update($id, $dto);

            return redirect()
                ->route('production.work-centers.show', $wc->id)
                ->with('success', "Work center '{$wc->name}' updated successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('delete', $workCenter);

        try {
            $this->service->delete($id);
            return redirect()
                ->route('production.work-centers.index')
                ->with('success', 'Work center deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
