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
        $machines = Machine::with('workCenter')
            ->active()
            ->orderBy('name')
            ->get();

        // Attach current operation to each machine
        $machines->each(function ($machine) {
            $machine->currentOp = ProductionScheduleOperation::with(['schedule.order.product', 'orderOperation'])
                ->where('machine_id', $machine->id)
                ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
                ->first();
        });

        return view('modules.production.mes.machine-dashboard', compact('machines'));
    }

    public function show(int $id)
    {
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

        return view('modules.production.mes.machine-detail', compact('machine', 'currentOp', 'nextOp', 'history'));
    }
}
