<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\DTO\MachineDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Repositories\MachineRepositoryInterface;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use App\Domains\Production\Requests\StoreMachineRequest;
use App\Domains\Production\Requests\UpdateMachineRequest;
use App\Domains\Production\Services\MachineService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function __construct(
        private readonly MachineRepositoryInterface $repository,
        private readonly WorkCenterRepositoryInterface $workCenterRepository,
        private readonly MachineService $service
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Machine::class);

        $filters      = $request->only(['work_center_id', 'status', 'search']);
        $machines     = $this->repository->paginateAll($filters, 15)->withQueryString();
        $workCenters  = $this->workCenterRepository->getActiveWorkCenters();
        $statuses     = config('production.machine_statuses', []);

        return view('modules.production.machines.index', compact('machines', 'workCenters', 'filters', 'statuses'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Machine::class);

        $workCenters = $this->workCenterRepository->getActiveWorkCenters();
        $statuses    = config('production.machine_statuses', []);
        $selectedWorkCenterId = $request->query('work_center_id');

        return view('modules.production.machines.create', compact('workCenters', 'statuses', 'selectedWorkCenterId'));
    }

    public function store(StoreMachineRequest $request): RedirectResponse
    {
        Gate::authorize('create', Machine::class);

        try {
            $tenantId = require_tenant_id();
            $dto      = MachineDTO::fromArray($request->validated());
            $machine  = $this->service->create($dto, $tenantId);

            return redirect()
                ->route('production.machines.index')
                ->with('success', "Machine '{$machine->name}' created successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $machine = $this->repository->find($id);
        abort_if(!$machine, 404, 'Machine not found.');

        Gate::authorize('update', $machine);

        $workCenters = $this->workCenterRepository->getActiveWorkCenters();
        $statuses    = config('production.machine_statuses', []);

        return view('modules.production.machines.edit', compact('machine', 'workCenters', 'statuses'));
    }

    public function update(UpdateMachineRequest $request, int $id): RedirectResponse
    {
        $machine = $this->repository->find($id);
        abort_if(!$machine, 404, 'Machine not found.');

        Gate::authorize('update', $machine);

        try {
            $dto     = MachineDTO::fromArray($request->validated());
            $machine = $this->service->update($id, $dto);

            return redirect()
                ->route('production.machines.index')
                ->with('success', "Machine '{$machine->name}' updated successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $machine = $this->repository->find($id);
        abort_if(!$machine, 404, 'Machine not found.');

        Gate::authorize('delete', $machine);

        try {
            $this->service->delete($id);
            return redirect()
                ->route('production.machines.index')
                ->with('success', 'Machine deleted successfully.');
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
                ->with('error', __('production.no_machines_selected'));
        }

        $tenantId = require_tenant_id();
        $machines = Machine::whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->get();

        $successCount = 0;
        $failedCount = 0;

        switch ($action) {
            case 'delete':
                foreach ($machines as $machine) {
                    if (auth()->user()->can('delete', $machine)) {
                        try {
                            $this->service->delete($machine->id);
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
                foreach ($machines as $machine) {
                    if (auth()->user()->can('update', $machine)) {
                        try {
                            $machine->update(['status' => 'active']);
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
                foreach ($machines as $machine) {
                    if (auth()->user()->can('update', $machine)) {
                        try {
                            $machine->update(['status' => 'inactive']);
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

            case 'maintenance':
                foreach ($machines as $machine) {
                    if (auth()->user()->can('update', $machine)) {
                        try {
                            $machine->update(['status' => 'maintenance']);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "set to maintenance status for";
                break;

            default:
                return redirect()->back()->with('error', 'Invalid action');
        }

        $message = "Successfully {$messagePrefix} {$successCount} " . \Illuminate\Support\Str::plural('machine', $successCount) . ".";
        if ($failedCount > 0) {
            $message .= " Failed to process {$failedCount} " . \Illuminate\Support\Str::plural('machine', $failedCount) . " due to constraints or permissions.";
        }

        return redirect()->back()->with($failedCount > 0 ? 'warning' : 'success', $message);
    }

    /**
     * Q5: AJAX endpoint — returns machines filtered by work center.
     */
    public function byWorkCenter(int $workCenter): JsonResponse
    {
        Gate::authorize('viewAny', Machine::class);

        $machines = $this->service->getMachinesForWorkCenter($workCenter, activeOnly: true);
        return response()->json($machines);
    }
}
