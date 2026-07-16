<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\SchedulingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class CapacityController extends Controller
{
    public function __construct(
        private readonly CapacityPlanningService $capacityService,
        private readonly SchedulingService $schedulingService
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', ProductionSchedule::class);

        $tenantId = require_tenant_id();

        // Standard bounded date range: default next 14 days
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->addDays(14)->endOfDay();

        // 1. Work Center capacity & utilization
        $workCenterLoads = $this->capacityService->getWorkCenterCapacity($tenantId, $startDate, $endDate);

        // 2. Machine capacity & utilization
        $machineLoads = $this->capacityService->getMachineCapacity($tenantId, $startDate, $endDate);

        // 3. Daily loads summary
        $dailyLoads = $this->capacityService->getDailyLoad($tenantId, $startDate, $endDate);

        // 4. Overloads and Scheduling conflicts
        $conflictMessages = $this->schedulingService->detectConflicts($tenantId);
        $overloadMessages = $this->schedulingService->detectOverloads($tenantId);

        // 5. Active operations inside the range for rescheduling/details
        $activeOperations = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereBetween('planned_start', [$startDate, $endDate])
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->with(['order.product', 'orderOperation', 'workCenter', 'machine'])
            ->orderBy('planned_start')
            ->get();

        // Fetch Work Centers and Machines for rescheduling selector lists
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $machines = Machine::where('tenant_id', $tenantId)->where('status', 'active')->get();

        return view('modules.production.capacity.index', compact(
            'workCenterLoads',
            'machineLoads',
            'dailyLoads',
            'conflictMessages',
            'overloadMessages',
            'activeOperations',
            'workCenters',
            'machines',
            'startDate',
            'endDate'
        ));
    }

    public function reschedule(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasProductionPermission('production.schedule.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'planned_start' => 'required|date',
            'machine_id'    => 'nullable|integer|exists:production_machines,id',
            'reason'        => 'nullable|string|max:500',
        ]);

        try {
            $newStart = Carbon::parse($request->input('planned_start'));
            $machineId = $request->filled('machine_id') ? (int) $request->input('machine_id') : null;
            $reason = $request->input('reason') ?: 'Manual reschedule';

            $this->capacityService->rescheduleOperation($id, $newStart, $machineId, $reason, auth()->id() ?: 1);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Operation rescheduled successfully.',
                ]);
            }

            return redirect()->back()->with('success', 'Operation rescheduled successfully.');
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reschedule: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->withInput()->with('error', 'Failed to reschedule operation.');
        }
    }

    public function suggest(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasProductionPermission('production.schedule.manage')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $suggestions = $this->capacityService->getLoadBalanceSuggestions($id);

            $formatted = collect($suggestions)->map(function($s) {
                return [
                    'machine_id' => $s['machine']->id,
                    'machine_name' => $s['machine']->name,
                    'machine_code' => $s['machine']->code,
                    'suggested_start' => $s['suggested_start']->toDateTimeString(),
                    'suggested_finish' => $s['suggested_finish']->toDateTimeString(),
                    'conflict_resolved' => $s['conflict_resolved'],
                    'warning' => $s['warning'],
                ];
            });

            return response()->json([
                'success' => true,
                'suggestions' => $formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suggestions: ' . $e->getMessage(),
            ], 500);
        }
    }
}
