<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Services\MesExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class MesController extends Controller
{
    public function __construct(
        private readonly MesExecutionService $mesService
    ) {}

    /**
     * Operator Dashboard — shows all operations assigned to or relevant for the current user.
     */
    public function dashboard(Request $request)
    {
        $tenantId  = require_tenant_id();
        $userId    = Auth::id();

        // Running operations in this tenant
        $running = ProductionScheduleOperation::with(['schedule.order.product', 'workCenter', 'machine'])
            ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
            ->orderBy('planned_start')
            ->get();

        // Ready queue — operations ready to start across active schedules
        $ready = ProductionScheduleOperation::with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn ($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_READY)
            ->orderBy('priority')
            ->orderBy('planned_start')
            ->get();

        // Upcoming — next waiting operations (not yet ready) for active schedules
        $upcoming = ProductionScheduleOperation::with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn ($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_WAITING)
            ->orderBy('planned_start')
            ->take(10)
            ->get();

        // Completed today
        $completedToday = ProductionScheduleOperation::with(['schedule.order.product'])
            ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->count();

        // Paused / on-hold
        $paused = ProductionScheduleOperation::with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn ($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_PAUSED)
            ->orderBy('planned_start')
            ->get();

        return view('modules.production.mes.dashboard', compact(
            'running', 'ready', 'upcoming', 'completedToday', 'paused'
        ));
    }

    public function start(Request $request, int $op)
    {
        try {
            $machineId = $request->input('machine_id') ? (int) $request->input('machine_id') : null;
            $this->mesService->startOperation($op, $machineId, Auth::id() ?: 1);
            return redirect()->back()->with('success', 'Operation started successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function pause(Request $request, int $op)
    {
        try {
            $this->mesService->pauseOperation($op, $request->input('remarks'));
            return redirect()->back()->with('success', 'Operation paused.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function resume(int $op)
    {
        try {
            $this->mesService->resumeOperation($op);
            return redirect()->back()->with('success', 'Operation resumed.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function complete(Request $request, int $op)
    {
        $data = $request->validate([
            'quantity_produced' => 'required|numeric|min:0',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'quantity_scrapped' => 'nullable|numeric|min:0',
            'setup_minutes'     => 'nullable|numeric|min:0',
            'run_minutes'       => 'nullable|numeric|min:0',
            'remarks'           => 'nullable|string|max:1000',
        ]);

        try {
            $this->mesService->completeOperation($op, $data, Auth::id() ?: 1);
            return redirect()->back()->with('success', 'Operation completed and progress logged.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function hold(Request $request, int $op)
    {
        try {
            $this->mesService->holdOperation($op, $request->input('remarks'));
            return redirect()->back()->with('success', 'Operation placed on hold.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, int $op)
    {
        try {
            $this->mesService->cancelOperation($op);
            return redirect()->back()->with('success', 'Operation cancelled.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
