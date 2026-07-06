<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;

class WorkCenterDashboardController extends Controller
{
    public function index()
    {
        $workCenters = WorkCenter::active()
            ->with(['machines'])
            ->orderBy('name')
            ->get();

        // Attach summary stats to each work center
        $workCenters->each(function ($wc) {
            $wc->runningCount = ProductionScheduleOperation::where('work_center_id', $wc->id)
                ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
                ->count();

            $wc->waitingCount = ProductionScheduleOperation::where('work_center_id', $wc->id)
                ->where('status', ProductionScheduleOperation::STATUS_READY)
                ->count();

            $wc->completedToday = ProductionScheduleOperation::where('work_center_id', $wc->id)
                ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
                ->whereDate('actual_finish', today())
                ->count();
        });

        return view('modules.production.mes.work-center-dashboard', compact('workCenters'));
    }

    public function show(int $id)
    {
        $workCenter = WorkCenter::with('machines')->findOrFail($id);

        // Ordered execution queue for this work center
        $queue = ProductionScheduleOperation::with([
            'schedule.order.product',
            'machine',
            'orderOperation',
        ])
        ->where('work_center_id', $workCenter->id)
        ->whereHas('schedule', fn ($q) =>
            $q->whereIn('status', [ProductionSchedule::STATUS_SCHEDULED, ProductionSchedule::STATUS_RELEASED])
        )
        ->whereNotIn('status', [
            ProductionScheduleOperation::STATUS_COMPLETED,
            ProductionScheduleOperation::STATUS_CANCELLED,
            ProductionScheduleOperation::STATUS_SKIPPED,
        ])
        ->orderBy('planned_start')
        ->orderBy('sequence')
        ->get();

        $completedToday = ProductionScheduleOperation::where('work_center_id', $workCenter->id)
            ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->count();

        // Utilization: sum of planned minutes vs available today (8h assumed)
        $plannedMinutes  = $queue->sum('planned_duration_minutes');
        $availableMinutes = 8 * 60 * ($workCenter->efficiency_percentage / 100);
        $utilization     = $availableMinutes > 0
            ? round(min(100, ($plannedMinutes / $availableMinutes) * 100), 1)
            : 0;

        return view('modules.production.mes.work-center-detail', compact(
            'workCenter', 'queue', 'completedToday', 'utilization', 'plannedMinutes', 'availableMinutes'
        ));
    }
}
