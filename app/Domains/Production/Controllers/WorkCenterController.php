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

use App\Domains\Production\Models\ProductionShift;

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
        $workCenters = $this->repository->paginateAll($filters, 15)->withQueryString();

        $workCenterTypes = config('production.work_center_types', []);

        return view('modules.production.work-centers.index', compact('workCenters', 'filters', 'workCenterTypes'));
    }

    public function create(): View
    {
        Gate::authorize('create', WorkCenter::class);

        $tenantId = require_tenant_id();
        $shifts = ProductionShift::where('tenant_id', $tenantId)->where('active', true)->orderBy('name')->get();
        $workCenterTypes = config('production.work_center_types', []);
        $parentOptions = WorkCenter::orderBy('name')->get();
        return view('modules.production.work-centers.create', compact('workCenterTypes', 'parentOptions', 'shifts'));
    }

    public function store(StoreWorkCenterRequest $request): RedirectResponse
    {
        Gate::authorize('create', WorkCenter::class);

        try {
            $tenantId = require_tenant_id();
            $data = $request->validated();
            if (isset($data['cost_per_hour'])) {
                $data['cost_per_hour'] = convert_to_base($data['cost_per_hour']);
            }
            $dto = WorkCenterDTO::fromArray($data);
            $wc  = $this->service->create($dto, $tenantId);

            // Sync shifts
            $shifts = $request->input('shifts', []);
            $syncData = [];
            foreach ($shifts as $shiftId) {
                $syncData[$shiftId] = ['tenant_id' => $tenantId];
            }
            $wc->shifts()->sync($syncData);

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

        $tenantId = require_tenant_id();
        $shifts = ProductionShift::where('tenant_id', $tenantId)->where('active', true)->orderBy('name')->get();
        $workCenterTypes = config('production.work_center_types', []);
        $parentOptions = WorkCenter::where('id', '!=', $id)->orderBy('name')->get();
        return view('modules.production.work-centers.edit', compact('workCenter', 'workCenterTypes', 'parentOptions', 'shifts'));
    }

    public function update(UpdateWorkCenterRequest $request, int $id): RedirectResponse
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('update', $workCenter);

        try {
            $tenantId = require_tenant_id();
            $data = $request->validated();
            if (isset($data['cost_per_hour'])) {
                $data['cost_per_hour'] = convert_to_base($data['cost_per_hour']);
            }
            $dto = WorkCenterDTO::fromArray($data);
            $wc  = $this->service->update($id, $dto);


            // Sync shifts
            $shifts = $request->input('shifts', []);
            $syncData = [];
            foreach ($shifts as $shiftId) {
                $syncData[$shiftId] = ['tenant_id' => $tenantId];
            }
            $wc->shifts()->sync($syncData);

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

    public function bulkAction(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $ids = $request->input('ids');

        if (empty($ids) || !is_array($ids)) {
            return redirect()
                ->back()
                ->with('error', __('production.no_work_centers_selected'));
        }

        $tenantId = require_tenant_id();
        $workCenters = WorkCenter::whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->get();

        $successCount = 0;
        $failedCount = 0;

        switch ($action) {
            case 'delete':
                foreach ($workCenters as $wc) {
                    if (auth()->user()->can('delete', $wc)) {
                        try {
                            $this->service->delete($wc->id);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "deleted";
                break;

            case 'activate':
                foreach ($workCenters as $wc) {
                    if (auth()->user()->can('update', $wc)) {
                        try {
                            $wc->update(['status' => 'active']);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "activated";
                break;

            case 'deactivate':
                foreach ($workCenters as $wc) {
                    if (auth()->user()->can('update', $wc)) {
                        try {
                            $wc->update(['status' => 'inactive']);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "deactivated";
                break;

            default:
                return redirect()->back()->with('error', 'Invalid action');
        }

        $message = "Successfully {$messagePrefix} {$successCount} " . \Illuminate\Support\Str::plural('work center', $successCount) . ".";
        if ($failedCount > 0) {
            $message .= " Failed to process {$failedCount} " . \Illuminate\Support\Str::plural('work center', $failedCount) . " due to constraints or permissions.";
        }

        return redirect()->back()->with($failedCount > 0 ? 'warning' : 'success', $message);
    }
}
