<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Requests\StoreProductionScheduleRequest;
use App\Domains\Production\Services\SchedulingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ProductionScheduleController extends Controller
{
    public function __construct(
        private readonly SchedulingService $schedulingService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ProductionSchedule::class);

        $query = ProductionSchedule::with(['order.product', 'creator']);

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
                ->with('success', "Schedule [{$schedule->schedule_number}] generated successfully.");
        } catch (\LogicException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to generate schedule: ' . $e->getMessage());
        }
    }

    public function show(int $id)
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
        $warnings = $this->schedulingService->detectOverloads($tenantId);

        return view('modules.production.schedules.show', compact('schedule', 'warnings'));
    }

    public function destroy(int $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);

        $this->authorize('delete', $schedule);

        if ($schedule->isFrozen()) {
            return redirect()->back()->with('error', 'Completed or cancelled schedules cannot be deleted.');
        }

        $schedule->delete();

        return redirect()
            ->route('production.schedules.index')
            ->with('success', "Schedule [{$schedule->schedule_number}] deleted successfully.");
    }

    public function release(int $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);

        $this->authorize('release', $schedule);

        if (!$schedule->isScheduled()) {
            return redirect()->back()->with('error', 'Only scheduled (confirmed) schedules can be released.');
        }

        try {
            $this->schedulingService->validateSchedule($schedule);

            $schedule->update([
                'status'      => ProductionSchedule::STATUS_RELEASED,
                'released_at' => now(),
                'released_by' => Auth::id() ?: 1,
            ]);

            return redirect()->back()->with('success', "Schedule [{$schedule->schedule_number}] released to shop floor.");
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
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
            'cancelled_by' => Auth::id() ?: 1,
        ]);

        return redirect()->back()->with('success', "Schedule [{$schedule->schedule_number}] cancelled.");
    }

    public function calendarView(Request $request)
    {
        $tenantId = require_tenant_id();

        $view = $request->input('view', 'week'); // day | week | month

        $startDate = $request->filled('start')
            ? Carbon::parse($request->input('start'))
            : now()->startOfWeek();

        $endDate = match ($view) {
            'day'   => $startDate->copy()->endOfDay(),
            'month' => $startDate->copy()->endOfMonth(),
            default => $startDate->copy()->endOfWeek(),
        };

        $operations = ProductionScheduleOperation::with([
            'schedule', 'order.product', 'workCenter', 'machine',
        ])
        ->whereHas('schedule', fn ($q) =>
            $q->whereIn('status', [
                ProductionSchedule::STATUS_SCHEDULED,
                ProductionSchedule::STATUS_RELEASED,
                ProductionSchedule::STATUS_IN_PROGRESS
            ])
        )
        ->whereBetween('planned_start', [$startDate, $endDate])
        ->orderBy('planned_start')
        ->get();

        return view('modules.production.schedules.calendar', compact('operations', 'startDate', 'endDate', 'view'));
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
}
