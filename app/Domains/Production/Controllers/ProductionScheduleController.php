<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Requests\StoreProductionScheduleRequest;
use App\Domains\Production\Requests\ProductionScheduleCalendarRequest;
use App\Domains\Production\Services\SchedulingService;
use App\Domains\Production\Services\SchedulingCalendarService;
use App\Domains\Production\Services\CapacityPlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use App\Domains\Production\Models\ProductionScheduleScenario;
use App\Domains\Production\Services\SchedulePreReleaseValidationService;
use App\Domains\Production\Services\CapacityLevelingService;
use App\Domains\Production\Services\ProductionScheduleScenarioService;

class ProductionScheduleController extends Controller
{
    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly SchedulingCalendarService $calendarService,
        private readonly CapacityPlanningService $capacityService,
        private readonly SchedulePreReleaseValidationService $validationService,
        private readonly CapacityLevelingService $capacityLevelingService,
        private readonly ProductionScheduleScenarioService $scenarioService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ProductionSchedule::class);

        $query = ProductionSchedule::with(['order.product', 'creator', 'operations']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('schedule_number', 'like', $search)
                  ->orWhereHas('order', function ($o) use ($search) {
                      $o->where('order_number', 'like', $search)
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $search));
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('scheduling_type')) {
            $query->where('scheduling_type', $request->input('scheduling_type'));
        }

        if ($request->filled('start_date')) {
            $query->whereHas('operations', fn ($q) =>
                $q->where('planned_start', '>=', $request->input('start_date'))
            );
        }

        $schedules = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $statusCounts = ProductionSchedule::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('modules.production.schedules.index', compact('schedules', 'statusCounts'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ProductionSchedule::class);

        $tenantId = require_tenant_id();

        // Only released production orders can be scheduled
        $orders = ProductionOrder::with('product')
            ->whereIn('status', [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS])
            ->get();

        return view('modules.production.schedules.create', compact('orders'));
    }

    public function store(StoreProductionScheduleRequest $request)
    {
        $this->authorize('create', ProductionSchedule::class);

        $tenantId = require_tenant_id();

        try {
            $order     = ProductionOrder::findOrFail($request->validated()['production_order_id']);
            $startDate = Carbon::parse($request->validated()['start_date']);
            $type      = $request->validated()['scheduling_type'];

            $schedule = $this->schedulingService->generateSchedule($order, $startDate, $type);

            // Apply optional notes
            if ($request->filled('notes')) {
                $schedule->update(['notes' => $request->input('notes')]);
            }

            return redirect()
                ->route('production.schedules.show', $schedule->id)
                ->with('success', "Scheduling created! Now you can release it to the shop floor.");
        } catch (\LogicException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to generate schedule: ' . $e->getMessage());
        }
    }

    public function show(Request $request, int $id)
    {
        $schedule = ProductionSchedule::with([
            'order.product',
            'order.routing',
            'operations.workCenter',
            'operations.machine',
            'operations.orderOperation',
            'creator',
            'releasedBy',
            'completedBy',
            'cancelledBy',
        ])->findOrFail($id);

        $this->authorize('view', $schedule);

        $tenantId = require_tenant_id();
        $overloads = $this->schedulingService->detectOverloads($tenantId);
        $conflicts = $this->schedulingService->detectConflicts($tenantId);
        $warnings = array_merge($overloads, $conflicts);
        $groupBy = $request->input('group_by');
        $capacityDetails = $this->schedulingService->getWorkCenterCapacityDetails($schedule, $groupBy);

        return view('modules.production.schedules.show', compact('schedule', 'warnings', 'capacityDetails', 'groupBy'));
    }

    public function destroy(int $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);

        $this->authorize('delete', $schedule);

        if ($schedule->isFrozen()) {
            return redirect()->back()->with('error', 'Completed or cancelled schedules cannot be deleted.');
        }

        if ($this->schedulingService->hasExecutionHistory($schedule)) {
            // Write alert event to timeline
            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($schedule->tenant_id, [
                'production_order_id' => $schedule->production_order_id,
                'event_type' => 'Schedule Blocked',
                'title' => 'Deletion Blocked',
                'description' => "Attempted deletion of Schedule [{$schedule->schedule_number}] was blocked because it has active execution or WIP records.",
                'severity' => 'danger',
                'event_source' => 'SchedulingService',
            ]);

            return redirect()->back()->with('error', 'Cannot delete schedule: Active MES execution, progress logs, or WIP movements exist.');
        }

        // Explicitly delete schedule operations
        \App\Domains\Production\Models\ProductionScheduleOperation::where('production_schedule_id', $schedule->id)->delete();
        $schedule->delete();

        return redirect()
            ->route('production.schedules.index')
            ->with('success', "Schedule [{$schedule->schedule_number}] deleted successfully.");
    }

    public function release(Request $request, int $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);

        $this->authorize('release', $schedule);

        if (!$schedule->isScheduled()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Only scheduled (confirmed) schedules can be released.'], 422);
            }
            return redirect()->back()->with('error', 'Only scheduled (confirmed) schedules can be released.');
        }

        // Run Server-side Pre-Release Validation
        $validationResult = $this->validationService->validate($schedule);

        if (!$validationResult['can_release']) {
            $errorMsgs = collect($validationResult['errors'])->pluck('message')->implode(' ');
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'           => false,
                    'message'           => 'Release blocked due to schedule errors: ' . $errorMsgs,
                    'validation_result' => $validationResult,
                ], 422);
            }
            return redirect()->back()->with('error', 'Release blocked: ' . $errorMsgs);
        }

        // Check Warnings requiring explicit confirmation
        if ($validationResult['has_warnings'] && !$request->boolean('confirm_warnings')) {
            $warningMsgs = collect($validationResult['warnings'])->pluck('message')->implode(' ');
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'               => false,
                    'requires_confirmation' => true,
                    'message'               => 'Schedule has warnings. Explicit confirmation required to proceed with release.',
                    'validation_result'     => $validationResult,
                ], 422);
            }
            return redirect()->back()->with('warning', 'Release requires confirmation: ' . $warningMsgs);
        }

        try {
            $schedule->update([
                'status'      => ProductionSchedule::STATUS_RELEASED,
                'released_at' => now(),
                'released_by' => auth()->id(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Schedule [{$schedule->schedule_number}] released to the shop floor successfully!",
                ]);
            }

            return redirect()
                ->route('production.mes.dashboard')
                ->with('success', "Schedule [{$schedule->schedule_number}] released to the shop floor successfully!");
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function dispatchBoardView(Request $request)
    {
        $this->authorize('viewAny', ProductionSchedule::class);
        $workCenters = WorkCenter::active()->get();
        return view('modules.production.schedules.dispatch-board', compact('workCenters'));
    }

    public function dispatchBoardData(Request $request)
    {
        $this->authorize('viewAny', ProductionSchedule::class);

        $request->validate([
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date',
            'work_center_id'      => 'nullable|integer',
            'machine_id'          => 'nullable|integer',
            'production_order_id' => 'nullable|integer',
            'schedule_id'         => 'nullable|integer',
            'status'              => 'nullable|string',
        ]);

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))
            : Carbon::now()->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))
            : $startDate->copy()->addDays(14)->endOfDay();

        $tenantId = require_tenant_id();

        try {
            $data = $this->capacityService->getDispatchBoardData(
                $tenantId,
                $startDate,
                $endDate,
                $request->only(['work_center_id', 'machine_id', 'production_order_id', 'schedule_id', 'status'])
            );

            return response()->json($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load dispatch board data: ' . $e->getMessage()], 500);
        }
    }

    public function changeHistory(Request $request, int $scheduleId)
    {
        $tenantId = require_tenant_id();
        $schedule = ProductionSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($scheduleId);

        $this->authorize('view', $schedule);

        $logs = \App\Domains\Production\Models\ProductionScheduleChangeLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_schedule_id', $schedule->id)
            ->with(['changedBy', 'operation.orderOperation', 'oldMachine', 'newMachine'])
            ->orderByDesc('created_at')
            ->paginate(15);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($logs);
        }

        return view('modules.production.schedules.change-history', compact('schedule', 'logs'));
    }

    public function preReleaseCheck(Request $request, int $scheduleId)
    {
        $schedule = ProductionSchedule::findOrFail($scheduleId);
        $this->authorize('view', $schedule);

        $result = $this->validationService->validate($schedule);

        return response()->json($result);
    }

    public function cancel(int $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);

        $this->authorize('cancel', $schedule);

        if ($schedule->isFrozen()) {
            return redirect()->back()->with('error', 'Schedule is already in a terminal state and cannot be cancelled.');
        }

        $schedule->update([
            'status'       => ProductionSchedule::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', "Schedule [{$schedule->schedule_number}] cancelled.");
    }

    public function rescheduleStart(Request $request, int $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);

        $this->authorize('create', ProductionSchedule::class);

        if ($schedule->isFrozen()) {
            return redirect()->back()->with('error', 'Cannot reschedule a schedule that is completed or cancelled.');
        }

        $request->validate([
            'start_date' => 'required|date',
        ]);

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $newSchedule = $this->schedulingService->reschedule(
                $schedule->id,
                $startDate,
                $schedule->scheduling_type ?? 'forward'
            );

            if ($schedule->notes) {
                $newSchedule->update(['notes' => $schedule->notes]);
            }

            $actualStart = $newSchedule->operations()->min('planned_start');
            $displayDate = $actualStart ? \Carbon\Carbon::parse($actualStart)->format('d/m/Y H:i') : $startDate->format('d/m/Y H:i');

            return redirect()
                ->route('production.schedules.show', $newSchedule->id)
                ->with('success', "Schedule updated. Earliest available working start: {$displayDate}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to reschedule schedule: ' . $e->getMessage());
        }
    }

    public function calendarView(ProductionScheduleCalendarRequest $request)
    {
        $tenantId = require_tenant_id();
        $validated = $request->validated();

        $data = $this->calendarService->buildCalendarData($tenantId, $validated);

        return view('modules.production.schedules.calendar', $data);
    }

    public function workCenterView(Request $request)
    {
        $tenantId    = require_tenant_id();
        $workCenters = WorkCenter::active()->with(['machines'])->get();

        // Load released schedule operations grouped by work center
        $operations = ProductionScheduleOperation::with([
            'schedule', 'order.product', 'machine', 'orderOperation',
        ])
        ->whereHas('schedule', fn ($q) =>
            $q->whereIn('status', [
                ProductionSchedule::STATUS_SCHEDULED,
                ProductionSchedule::STATUS_RELEASED,
                ProductionSchedule::STATUS_IN_PROGRESS
            ])
        )
        ->whereNotIn('status', [
            ProductionScheduleOperation::STATUS_COMPLETED,
            ProductionScheduleOperation::STATUS_CANCELLED,
            ProductionScheduleOperation::STATUS_SKIPPED,
        ])
        ->orderBy('sequence')
        ->get()
        ->groupBy('work_center_id');

        return view('modules.production.schedules.work-center-view', compact('workCenters', 'operations'));
    }

    public function adjustOperation(Request $request, int $operationId)
    {
        $this->authorize('create', ProductionSchedule::class);

        $request->validate([
            'planned_start'    => 'required|date',
            'machine_id'       => 'nullable|integer|exists:production_machines,id',
            'shift_mode'       => 'nullable|string|in:isolated,ripple',
            'reason'           => 'nullable|string|max:500',
            'expected_version' => 'nullable|integer',
        ]);

        try {
            $newStart        = Carbon::parse($request->input('planned_start'));
            $newMachineId    = $request->filled('machine_id') ? (int) $request->input('machine_id') : null;
            $shiftMode       = $request->input('shift_mode', \App\Domains\Production\Models\ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED);
            $reason          = $request->input('reason');
            $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

            $result = $this->capacityService->rescheduleOperationWithMode(
                $operationId,
                $newStart,
                $newMachineId,
                $shiftMode,
                $reason,
                auth()->id(),
                $expectedVersion
            );

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->back()->with('success', $result['message']);
        } catch (\InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'CONCURRENCY_CONFLICT') ? 409 : 422;
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\LogicException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to adjust operation: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()->with('error', 'Failed to adjust operation.');
        }
    }

    public function toggleLock(Request $request, int $operationId)
    {
        $this->authorize('create', ProductionSchedule::class);

        try {
            $schedOp = $this->capacityService->toggleOperationLock($operationId, auth()->id());
            $statusStr = $schedOp->locked ? 'locked' : 'unlocked';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'locked'  => $schedOp->locked,
                    'version' => $schedOp->version,
                    'message' => "Operation successfully {$statusStr}.",
                ]);
            }

            return redirect()->back()->with('success', "Operation successfully {$statusStr}.");
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function previewCapacityLeveling(Request $request)
    {
        $this->authorize('create', ProductionSchedule::class);

        $validated = $request->validate([
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'work_center_id' => 'nullable|integer',
            'machine_id'     => 'nullable|integer',
            'schedule_id'    => 'nullable|integer',
        ]);

        try {
            $tenantId = auth()->user()->tenant_id;
            $userId   = auth()->id();

            $result = $this->capacityLevelingService->generatePreview($tenantId, $validated, $userId);

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to generate capacity leveling preview: ' . $e->getMessage()], 500);
        }
    }

    public function applyCapacityLeveling(Request $request)
    {
        $this->authorize('create', ProductionSchedule::class);

        $validated = $request->validate([
            'run_id' => 'required|integer',
        ]);

        try {
            $tenantId = auth()->user()->tenant_id;
            $userId   = auth()->id();

            $result = $this->capacityLevelingService->applyPreview($tenantId, (int) $validated['run_id'], $userId);

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'OPTIMIZATION_PREVIEW_STALE') ? 409 : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to apply capacity leveling preview: ' . $e->getMessage()], 500);
        }
    }

    // ── Phase 6 — What-If Planning & Schedule Scenarios ─────────────────────
    public function scenariosIndex(Request $request)
    {
        $this->authorize('viewAny', ProductionSchedule::class);
        $tenantId = require_tenant_id();

        $scenarios = ProductionScheduleScenario::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['creator', 'sourceSchedule'])
            ->orderBy('id', 'desc')
            ->get();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($scenarios);
        }

        return view('modules.production.schedules.scenarios.index', compact('scenarios'));
    }

    public function storeScenario(Request $request)
    {
        $this->authorize('create', ProductionSchedule::class);

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'scenario_type'        => 'nullable|string',
            'source_schedule_id'   => 'nullable|integer',
            'work_center_id'       => 'nullable|integer',
            'machine_id'           => 'nullable|integer',
            'start_date'           => 'nullable|date',
            'end_date'             => 'nullable|date',
            'assumptions'          => 'nullable|array',
            'production_order_ids' => 'nullable|array',
        ]);

        try {
            $tenantId = require_tenant_id();
            $userId   = auth()->id();

            $scenario = $this->scenarioService->createScenario($tenantId, $validated, $userId);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'scenario' => $scenario]);
            }

            return redirect()->back()->with('success', "What-If Scenario [{$scenario->name}] created successfully.");
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create scenario: ' . $e->getMessage()], 500);
        }
    }

    public function recalculateScenario(Request $request, int $scenario)
    {
        $this->authorize('create', ProductionSchedule::class);

        try {
            $tenantId = require_tenant_id();
            $result   = $this->scenarioService->recalculateScenario($tenantId, $scenario);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to recalculate scenario: ' . $e->getMessage()], 500);
        }
    }

    public function levelScenarioCapacity(Request $request, int $scenario)
    {
        $this->authorize('create', ProductionSchedule::class);

        try {
            $tenantId = require_tenant_id();
            $result   = $this->scenarioService->levelScenarioCapacity($tenantId, $scenario);

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to level scenario capacity: ' . $e->getMessage()], 500);
        }
    }

    public function compareScenario(Request $request, int $scenario)
    {
        $this->authorize('viewAny', ProductionSchedule::class);

        try {
            $tenantId   = require_tenant_id();
            $comparison = $this->scenarioService->compareWithLive($tenantId, $scenario);

            return response()->json($comparison);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to compare scenario: ' . $e->getMessage()], 500);
        }
    }

    public function promoteScenario(Request $request, int $scenario)
    {
        $this->authorize('create', ProductionSchedule::class);

        try {
            $tenantId = require_tenant_id();
            $userId   = auth()->id();

            $result = $this->scenarioService->promoteScenario($tenantId, $scenario, $userId);

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'SCENARIO_STALE') ? 409 : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to promote scenario: ' . $e->getMessage()], 500);
        }
    }

    public function discardScenario(Request $request, int $scenario)
    {
        $this->authorize('create', ProductionSchedule::class);

        try {
            $tenantId = require_tenant_id();
            $result   = $this->scenarioService->discardScenario($tenantId, $scenario);

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to discard scenario: ' . $e->getMessage()], 500);
        }
    }
}
