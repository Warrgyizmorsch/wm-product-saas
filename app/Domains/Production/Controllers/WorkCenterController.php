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
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Services\CountdownCalculationEngine;
use Illuminate\Http\JsonResponse;

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
        $workCenterTypes = config('production.work_center_types', []);
        $calendars = ProductionCalendar::where('tenant_id', $tenantId)->orderBy('name')->get();
        return view('modules.production.work-centers.create', compact('workCenterTypes', 'calendars'));
    }

    public function store(StoreWorkCenterRequest $request): RedirectResponse
    {
        Gate::authorize('create', WorkCenter::class);

        try {
            $tenantId = require_tenant_id();
            $data = $request->validated();
            unset($data['capacity_per_hour'], $data['efficiency_percentage'], $data['cost_per_hour']);
            if (isset($data['overhead_cost'])) {
                $data['overhead_cost'] = convert_to_base($data['overhead_cost']);
            }
            $dto = WorkCenterDTO::fromArray($data);
            $wc  = $this->service->create($dto, $tenantId);

            return redirect()
                ->route('production.work-centers.show', $wc->id)
                ->with('success', "Work center '{$wc->name}' created successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('view', $workCenter);

        $tenantId = require_tenant_id();
        $eligibleMachines = \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)
            ->whereNull('section_id')
            ->where('status', '!=', \App\Domains\Production\Models\Machine::STATUS_DECOMMISSIONED)
            ->orderBy('name')
            ->get();

        $availableShifts = ProductionShift::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $activePayGrades = \App\Domains\Production\Models\ExternalWorkerPayGrade::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $availableEmployees = class_exists(\App\Domains\HRMS\Models\Employee::class)
            ? \App\Domains\HRMS\Models\Employee::with(['designation', 'department', 'shift'])
                ->where('tenant_id', $tenantId)
                ->orderBy('full_name')
                ->get()
            : collect();

        $employeesWithActiveSection = \App\Domains\Production\Models\WorkCenterEmployeeAssignment::where('tenant_id', $tenantId)
            ->whereNotNull('section_id')
            ->where('active', true)
            ->with('section')
            ->get()
            ->keyBy('employee_id');

        $employeesWithActiveWorkCenter = \App\Domains\Production\Models\WorkCenterEmployeeAssignment::where('tenant_id', $tenantId)
            ->whereNull('section_id')
            ->where('active', true)
            ->with('workCenter')
            ->get()
            ->groupBy('employee_id');

        $countdownEngine = app(CountdownCalculationEngine::class);
        $isCountdownActive = $countdownEngine->isCountdownActive($workCenter);
        $countdownRemainingSeconds = ($workCenter->calculation_countdown_end_at && $isCountdownActive)
            ? max(0, (int) now()->diffInSeconds($workCenter->calculation_countdown_end_at, false))
            : 0;

        $workCenter->load([
            'latestCalculation',
            'sections.latestCalculation',
            'sections.machines',
            'sections.manualProductions.employees.employee.shift',
            'sections.manualProductions.externalAllocations.payGrade',
            'sections.manualProductions.externalAllocations.shift',
            'sections.manualProductions.shift',
            'sections.externalWorkerAllocations.payGrade',
            'sections.externalWorkerAllocations.shift',
            'sections.employeeAssignments.employee.shift',
            'employeeAssignments.employee.shift',
            'activityLogs' => fn($q) => $q->latest()->take(20),
        ]);

        return view('modules.production.work-centers.show', compact(
            'workCenter',
            'eligibleMachines',
            'availableShifts',
            'activePayGrades',
            'availableEmployees',
            'employeesWithActiveSection',
            'employeesWithActiveWorkCenter',
            'isCountdownActive',
            'countdownRemainingSeconds'
        ));
    }

    public function edit(int $id): View
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('update', $workCenter);

        $tenantId = require_tenant_id();
        $workCenterTypes = config('production.work_center_types', []);
        $calendars = ProductionCalendar::where('tenant_id', $tenantId)->orderBy('name')->get();
        return view('modules.production.work-centers.edit', compact('workCenter', 'workCenterTypes', 'calendars'));
    }

    public function update(UpdateWorkCenterRequest $request, int $id): RedirectResponse
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');

        Gate::authorize('update', $workCenter);

        try {
            $tenantId = require_tenant_id();
            $data = $request->validated();
            unset($data['capacity_per_hour'], $data['efficiency_percentage'], $data['cost_per_hour']);
            $data['calculation_countdown_minutes'] = $workCenter->calculation_countdown_minutes ?? 10;
            if (isset($data['overhead_cost'])) {
                $data['overhead_cost'] = convert_to_base($data['overhead_cost']);
            }
            $dto = WorkCenterDTO::fromArray($data);
            $wc  = $this->service->update($id, $dto);

            return redirect()
                ->route('production.work-centers.show', $wc->id)
                ->with('success', "Work center '{$wc->name}' updated successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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

    public function recalculateNow(int $id, CountdownCalculationEngine $engine): RedirectResponse|JsonResponse
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');
        Gate::authorize('update', $workCenter);

        try {
            $snapshot = $engine->recalculateNow($workCenter, 'manual');
            $msg = "Work Center '{$workCenter->name}' recalculated successfully. Daily Capacity: {$snapshot->daily_capacity} units.";
            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg, 'snapshot' => $snapshot]);
            }
            return redirect()->route('production.work-centers.show', $workCenter->id)->with('success', $msg);
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function updateCountdownDuration(Request $request, int $id, CountdownCalculationEngine $engine): RedirectResponse|JsonResponse
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');
        Gate::authorize('update', $workCenter);

        $validated = $request->validate([
            'calculation_countdown_minutes' => 'required|integer|min:1|max:1440',
        ]);

        try {
            $updated = $engine->updateCountdownDuration($workCenter, (int) $validated['calculation_countdown_minutes'], auth()->id());
            $msg = "Countdown debounce duration updated to {$updated->calculation_countdown_minutes} minutes.";
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg, 'minutes' => $updated->calculation_countdown_minutes]);
            }
            return redirect()->route('production.work-centers.show', $workCenter->id)->with('success', $msg);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function calculationDetails(int $id): JsonResponse
    {
        $workCenter = $this->repository->find($id);
        abort_if(!$workCenter, 404, 'Work center not found.');
        Gate::authorize('view', $workCenter);

        $latestSnapshot = $workCenter->latestCalculation;
        // TEMPORARY AUDIT OVERRIDE: Blocked auto-calculation on calculationDetails GET request.
        // if (!$latestSnapshot) {
        //     $calcService = app(\App\Domains\Production\Services\WorkCenterCalculationService::class);
        //     $latestSnapshot = $calcService->calculateAndSave($workCenter);
        // }

        return response()->json([
            'success'  => true,
            'snapshot' => $latestSnapshot,
        ]);
    }
}

