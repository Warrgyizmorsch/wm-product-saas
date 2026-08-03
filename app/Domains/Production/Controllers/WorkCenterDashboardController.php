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
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $workCenters = WorkCenter::where('tenant_id', $tenantId)
            ->active()
            ->with(['machines'])
            ->orderBy('name')
            ->get();

        $wcIds = $workCenters->pluck('id')->toArray();

        // 1. Grouped running counts
        $runningCounts = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('work_center_id', $wcIds)
            ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
            ->selectRaw('work_center_id, count(*) as count')
            ->groupBy('work_center_id')
            ->pluck('count', 'work_center_id');

        // 2. Grouped waiting counts
        $waitingCounts = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('work_center_id', $wcIds)
            ->where('status', ProductionScheduleOperation::STATUS_READY)
            ->selectRaw('work_center_id, count(*) as count')
            ->groupBy('work_center_id')
            ->pluck('count', 'work_center_id');

        // 3. Grouped completed today counts
        $completedTodayCounts = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('work_center_id', $wcIds)
            ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->selectRaw('work_center_id, count(*) as count')
            ->groupBy('work_center_id')
            ->pluck('count', 'work_center_id');

        // Attach summary stats to each work center
        $workCenters->each(function ($wc) use ($runningCounts, $waitingCounts, $completedTodayCounts) {
            $wc->runningCount   = $runningCounts->get($wc->id, 0);
            $wc->waitingCount   = $waitingCounts->get($wc->id, 0);
            $wc->completedToday = $completedTodayCounts->get($wc->id, 0);
        });

        return view('modules.production.mes.work-center-dashboard', compact('workCenters'));
    }

    public function show(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $workCenter = WorkCenter::with('machines')->findOrFail($id);

        // Ordered execution queue for this work center
        $queue = ProductionScheduleOperation::with([
            'schedule.order.product',
            'machine',
            'orderOperation',
        ])
        ->where('work_center_id', $workCenter->id)
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
        ->orderBy('planned_start')
        ->orderBy('sequence')
        ->get();

        $completedToday = ProductionScheduleOperation::where('work_center_id', $workCenter->id)
            ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->count();

        // Utilization: sum of planned minutes vs available today (active shifts or 8h fallback)
        $schedulingService = app(\App\Domains\Production\Services\SchedulingService::class);
        $shifts = $workCenter->shifts()->where('active', true)->get();

        // 1. Available Capacity Today (in minutes) considering all active machines & efficiency
        $availableMinutes = $schedulingService->calculateCapacity($workCenter->id, today());
        if ($availableMinutes <= 0) {
            // Fallback for non-working days or unconfigured calendars (8h default per active machine)
            $machineCount = $workCenter->machines()->where('status', \App\Domains\Production\Models\Machine::STATUS_ACTIVE)->count() ?: 1;
            $availableMinutes = (8 * 60) * $machineCount * (($workCenter->efficiency_percentage ?? 100.0) / 100.0);
        }

        // 2. Planned minutes scheduled/active for TODAY
        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();

        $plannedMinutesToday = $queue->filter(function ($op) use ($todayStart, $todayEnd) {
            if ($op->status === ProductionScheduleOperation::STATUS_RUNNING) {
                return true;
            }
            if ($op->actual_start && $op->actual_start->gte($todayStart)) {
                return true;
            }
            if (!$op->planned_start || !$op->planned_finish) return false;
            return $op->planned_start->lt($todayEnd) && $op->planned_finish->gt($todayStart);
        })->sum(function ($op) use ($todayStart, $todayEnd, $workCenter) {
            if ($op->status === ProductionScheduleOperation::STATUS_RUNNING || ($op->actual_start && $op->actual_start->gte($todayStart))) {
                $shifts = $workCenter->shifts()->where('active', true)->get();
                $shiftMin = 480.0;
                if ($shifts->isNotEmpty()) {
                    $shiftMin = 0.0;
                    foreach ($shifts as $s) {
                        $st = \Carbon\Carbon::parse($s->start_time);
                        $et = \Carbon\Carbon::parse($s->end_time);
                        if ($et->lt($st)) $et->addDay();
                        $diff = $st->diffInMinutes($et);
                        if ($s->break_minutes > 0) $diff -= $s->break_minutes;
                        $shiftMin += max(0.0, $diff);
                    }
                }
                $perMachineCap = $shiftMin * (($workCenter->efficiency_percentage ?? 100.0) / 100.0);
                return min($perMachineCap, (float) ($op->planned_duration_minutes ?: $perMachineCap));
            }
            $start = $op->planned_start->max($todayStart);
            $finish = $op->planned_finish->min($todayEnd);
            return max(0, $start->diffInMinutes($finish));
        });

        // 3. Actual elapsed execution minutes worked TODAY (running & paused active elapsed + completed actuals)
        $now = now();
        $activeElapsedToday = $queue->whereIn('status', [
            ProductionScheduleOperation::STATUS_RUNNING,
            ProductionScheduleOperation::STATUS_PAUSED,
        ])->sum(function ($op) use ($now, $todayStart) {
            if (!$op->actual_start) return 0.0;
            $start = $op->actual_start->max($todayStart);
            $end = ($op->status === ProductionScheduleOperation::STATUS_PAUSED && $op->last_paused_at)
                ? $op->last_paused_at
                : $now;
            $pausedSec = $op->accumulated_paused_seconds ?? 0;
            return max(0.0, round(($end->timestamp - $start->timestamp - $pausedSec) / 60, 1));
        });

        $completedActualsToday = ProductionScheduleOperation::where('work_center_id', $workCenter->id)
            ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->with('orderOperation')
            ->get()
            ->sum(function ($op) {
                return $op->orderOperation
                    ? ((float) $op->orderOperation->setup_time_actual + (float) $op->orderOperation->processing_time_actual)
                    : 0.0;
            });

        $actualMinutesToday = $activeElapsedToday + $completedActualsToday;

        // 4. Total queue backlog duration (in minutes) across all future scheduled dates
        $totalQueueMinutes = $queue->sum('planned_duration_minutes');

        $actualUtilization = $availableMinutes > 0
            ? round(min(100.0, ($actualMinutesToday / $availableMinutes) * 100.0), 1)
            : 0;

        $plannedUtilization = $availableMinutes > 0
            ? round(min(100.0, ($plannedMinutesToday / $availableMinutes) * 100.0), 1)
            : 0;

        // $utilization defaults to actual execution utilization for MES shop floor tracking
        $utilization = $actualUtilization;

        return view('modules.production.mes.work-center-detail', compact(
            'workCenter', 'queue', 'completedToday', 'utilization', 'actualUtilization', 'plannedUtilization', 'actualMinutesToday', 'plannedMinutesToday', 'totalQueueMinutes', 'availableMinutes', 'shifts'
        ));
    }
}
