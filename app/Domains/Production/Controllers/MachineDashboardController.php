<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;

class MachineDashboardController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $machines = Machine::where('tenant_id', $tenantId)
            ->with('workCenter')
            ->active()
            ->orderBy('name')
            ->get();

        $machineIds = $machines->pluck('id')->toArray();

        // Fetch all running operations in a single query to eliminate N+1
        $runningOps = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('machine_id', $machineIds)
            ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
            ->with(['schedule.order.product', 'orderOperation'])
            ->get()
            ->keyBy('machine_id');

        $machines->each(function ($machine) use ($runningOps) {
            $machine->currentOp = $runningOps->get($machine->id);
        });

        return view('modules.production.mes.machine-dashboard', compact('machines'));
    }

    public function show(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $machine = Machine::with('workCenter')->findOrFail($id);

        $currentOp = ProductionScheduleOperation::with(['schedule.order.product', 'orderOperation', 'workCenter'])
            ->where('machine_id', $machine->id)
            ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
            ->first();

        $nextOp = ProductionScheduleOperation::with(['schedule.order.product', 'orderOperation'])
            ->where('machine_id', $machine->id)
            ->where('status', ProductionScheduleOperation::STATUS_READY)
            ->orderBy('planned_start')
            ->first();

        $history = ProductionScheduleOperation::with(['schedule.order.product'])
            ->where('machine_id', $machine->id)
            ->whereIn('status', [
                ProductionScheduleOperation::STATUS_COMPLETED,
                ProductionScheduleOperation::STATUS_CANCELLED,
            ])
            ->orderByDesc('actual_finish')
            ->take(20)
            ->get();

        $stateHistories = \App\Domains\Production\Models\ProductionMachineStateHistory::with('changer')
            ->where('machine_id', $machine->id)
            ->orderByDesc('started_at')
            ->take(10)
            ->get();

        $downtimes = \App\Domains\Production\Models\ProductionMachineDowntime::with(['creator', 'approver', 'order'])
            ->where('machine_id', $machine->id)
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('modules.production.mes.machine-detail', compact('machine', 'currentOp', 'nextOp', 'history', 'stateHistories', 'downtimes'));
    }
}
