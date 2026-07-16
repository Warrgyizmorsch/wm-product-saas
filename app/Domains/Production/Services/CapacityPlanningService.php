<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Services\ProductionEventService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CapacityPlanningService
{
    public function __construct(
        private readonly SchedulingService $schedulingService
    ) {}

    /**
     * Get capacity planning details for work centers.
     */
    public function getWorkCenterCapacity(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $details = [];

        $allConflicts = $this->schedulingService->detectConflicts($tenantId);

        foreach ($workCenters as $wc) {
            $totalCapacity = 0.0;
            $date = $startDate->copy()->startOfDay();
            while ($date->lte($endDate)) {
                $totalCapacity += $this->schedulingService->calculateCapacity($wc->id, $date);
                $date->addDay();
            }

            $ops = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->where('work_center_id', $wc->id)
                ->whereBetween('planned_start', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
                ->with('orderOperation')
                ->get();

            $required = (float) $ops->sum('planned_duration_minutes');
            $setup = (float) $ops->sum(fn($o) => (float) ($o->orderOperation?->setup_time_planned ?? 0.0));
            $run = max(0.0, $required - $setup);

            $downtime = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
                ->where('work_center_id', $wc->id)
                ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->sum('duration_minutes');

            $available = max(0.0, $totalCapacity - $downtime);
            $utilization = $available > 0 ? ($required / $available) * 100 : ($required > 0 ? 100.0 : 0.0);
            $overload = max(0.0, $required - $available);
            $free = max(0.0, $available - $required);

            $status = 'available';
            if ($available <= 0) {
                $status = 'unavailable';
            } elseif ($utilization > 100) {
                $status = 'overloaded';
            } elseif ($utilization > 85) {
                $status = 'near_capacity';
            } elseif ($utilization > 0) {
                $status = 'balanced';
            }

            $wcConflicts = collect($allConflicts)->filter(function($c) use ($wc) {
                return str_contains(strtolower($c), strtolower($wc->name)) || str_contains($c, "Work Center #{$wc->id}");
            })->count();

            $details[] = [
                'work_center' => $wc,
                'available_hours' => round($available / 60.0, 2),
                'setup_hours' => round($setup / 60.0, 2),
                'run_hours' => round($run / 60.0, 2),
                'required_hours' => round($required / 60.0, 2),
                'utilization' => round($utilization, 2),
                'overload_hours' => round($overload / 60.0, 2),
                'free_hours' => round($free / 60.0, 2),
                'ops_count' => $ops->count(),
                'conflicts_count' => $wcConflicts,
                'status' => $status,
            ];
        }

        return $details;
    }

    /**
     * Get capacity planning details for machines.
     */
    public function getMachineCapacity(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $machines = Machine::where('tenant_id', $tenantId)->where('status', 'active')->with('workCenter')->get();
        $details = [];

        foreach ($machines as $m) {
            $wc = $m->workCenter;
            if (!$wc) continue;

            $totalCapacity = 0.0;
            $date = $startDate->copy()->startOfDay();
            while ($date->lte($endDate)) {
                $totalCapacity += $this->schedulingService->calculateCapacity($wc->id, $date);
                $date->addDay();
            }

            $ops = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->where('machine_id', $m->id)
                ->whereBetween('planned_start', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
                ->get();

            $required = (float) $ops->sum('planned_duration_minutes');

            $downtime = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
                ->where('machine_id', $m->id)
                ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->sum('duration_minutes');

            $available = max(0.0, $totalCapacity - $downtime);
            $utilization = $available > 0 ? ($required / $available) * 100 : ($required > 0 ? 100.0 : 0.0);
            $overload = max(0.0, $required - $available);

            $status = 'available';
            if ($m->isUnderMaintenance()) {
                $status = 'downtime';
            } elseif ($available <= 0) {
                $status = 'unavailable';
            } elseif ($utilization > 100) {
                $status = 'overloaded';
            } elseif ($utilization > 85) {
                $status = 'near_capacity';
            } elseif ($utilization > 0) {
                $status = 'balanced';
            }

            $details[] = [
                'machine' => $m,
                'available_hours' => round($available / 60.0, 2),
                'required_hours' => round($required / 60.0, 2),
                'utilization' => round($utilization, 2),
                'downtime_hours' => round($downtime / 60.0, 2),
                'overload_hours' => round($overload / 60.0, 2),
                'ops_count' => $ops->count(),
                'status' => $status,
            ];
        }

        return $details;
    }

    /**
     * Get capacity planner daily load grid data.
     */
    public function getDailyLoad(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $days = [];

        // Pre-fetch all operations for date range in a single query
        $allOps = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereBetween('planned_start', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->get();

        // Pre-fetch all machine downtimes in a single query
        $allDowntimes = ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $date = $startDate->copy()->startOfDay();
        while ($date->lte($endDate)) {
            $currentDateStr = $date->toDateString();
            foreach ($workCenters as $wc) {
                $capacity = $this->schedulingService->calculateCapacity($wc->id, $date);

                // Filter operations in-memory
                $ops = $allOps->filter(function ($op) use ($wc, $currentDateStr) {
                    return $op->work_center_id === $wc->id 
                        && $op->planned_start->toDateString() === $currentDateStr;
                });

                $required = (float) $ops->sum('planned_duration_minutes');

                // Filter downtimes in-memory
                $downtime = (float) $allDowntimes->filter(function ($dt) use ($wc, $currentDateStr) {
                    return $dt->work_center_id === $wc->id 
                        && $dt->start_time->toDateString() === $currentDateStr;
                })->sum('duration_minutes');

                $available = max(0.0, $capacity - $downtime);
                $utilization = $available > 0 ? ($required / $available) * 100 : ($required > 0 ? 100.0 : 0.0);

                $days[] = [
                    'date' => $currentDateStr,
                    'work_center' => $wc,
                    'available_hours' => round($available / 60.0, 2),
                    'used_hours' => round($required / 60.0, 2),
                    'remaining_hours' => round(max(0.0, $available - $required) / 60.0, 2),
                    'utilization' => round($utilization, 2),
                    'overloaded' => $required > $available,
                ];
            }
            $date->addDay();
        }

        return $days;
    }

    /**
     * Reschedule a single schedule operation step safely.
     */
    public function rescheduleOperation(
        int     $schedOpId,
        Carbon  $newStart,
        ?int    $newMachineId = null,
        ?string $reason       = null,
        ?int    $userId       = null
    ): void {
        DB::transaction(function () use ($schedOpId, $newStart, $newMachineId, $reason, $userId) {
            $schedOp = ProductionScheduleOperation::lockForUpdate()->findOrFail($schedOpId);
            $tenantId = require_tenant_id();

            if ($schedOp->tenant_id !== $tenantId) {
                throw new InvalidArgumentException("Operation does not belong to your tenant context.");
            }

            if ($schedOp->locked) {
                throw new InvalidArgumentException("Operation schedule is locked and cannot be moved.");
            }

            $order = $schedOp->order;
            if ($order->isClosed() || $order->isCancelled()) {
                throw new InvalidArgumentException("Operation cannot be rescheduled: Parent production order is closed or cancelled.");
            }

            $oldStart = $schedOp->planned_start->toDateTimeString();
            $oldFinish = $schedOp->planned_finish->toDateTimeString();
            $oldMachineName = $schedOp->machine ? $schedOp->machine->name : 'N/A';

            // Validate sequence timeline rules
            // Predecessor must finish before newStart
            $predecessor = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->where('sequence', '<', $schedOp->sequence)
                ->whereNotIn('status', ['cancelled', 'skipped'])
                ->orderBy('sequence', 'desc')
                ->first();

            if ($predecessor && $newStart->lt($predecessor->planned_finish)) {
                throw new InvalidArgumentException("Reschedule conflict: Starts before predecessor finishes (Predecessor finishes at: {$predecessor->planned_finish->toDateTimeString()}).");
            }

            // Successor must start after newFinish
            $newFinish = $newStart->copy()->addMinutes($schedOp->planned_duration_minutes);

            $successor = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->where('sequence', '>', $schedOp->sequence)
                ->whereNotIn('status', ['cancelled', 'skipped'])
                ->orderBy('sequence', 'asc')
                ->first();

            if ($successor && $newFinish->gt($successor->planned_start)) {
                throw new InvalidArgumentException("Reschedule conflict: Finishes after successor starts (Successor starts at: {$successor->planned_start->toDateTimeString()}).");
            }

            // Validate Working Day & Holiday rules
            $wc = $schedOp->workCenter;
            $calendar = $this->resolveCalendarForWorkCenter($wc, $tenantId);
            if (!$this->isWorkingDay($calendar, $newStart, $tenantId)) {
                throw new InvalidArgumentException("Reschedule failed: Target date is not a valid working day on calendar.");
            }

            // Validate Machine Eligibility
            if ($newMachineId) {
                $machine = Machine::findOrFail($newMachineId);
                if ($machine->work_center_id !== $schedOp->work_center_id) {
                    throw new InvalidArgumentException("Reschedule failed: Selected machine does not belong to Work Center.");
                }
                if (!$machine->isActive() || $machine->isUnderMaintenance()) {
                    throw new InvalidArgumentException("Reschedule failed: Selected machine is inactive or under maintenance.");
                }
            }

            // Update
            $schedOp->planned_start = $newStart;
            $schedOp->planned_finish = $newFinish;
            if ($newMachineId) {
                $schedOp->machine_id = $newMachineId;
            }
            $schedOp->save();

            // Sync to the Order Operation timings
            $orderOp = $schedOp->orderOperation;
            if ($orderOp) {
                $orderOp->update([
                    'setup_time_planned' => $orderOp->setup_time_planned, // remains standard
                ]);
            }

            // Write Timeline Audit Event log
            $newMachineName = $schedOp->machine ? $schedOp->machine->name : 'N/A';
            $userName = auth()->user() ? auth()->user()->name : 'System';

            app(ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $schedOp->production_order_id,
                'event_type'          => 'Schedule Rescheduled',
                'title'               => 'Operation Rescheduled',
                'description'         => "Rescheduled sequence {$schedOp->sequence} by {$userName}. Reason: {$reason}. " .
                                         "Old Slot: {$oldStart} to {$oldFinish} (Machine: {$oldMachineName}). " .
                                         "New Slot: {$newStart->toDateTimeString()} to {$newFinish->toDateTimeString()} (Machine: {$newMachineName}).",
                'severity'            => 'info',
                'event_source'        => 'CapacityPlanningService',
                'triggered_by'        => $userId,
            ]);
        });
    }

    /**
     * Get rule-based load balancing suggestions for an overloaded operation.
     */
    public function getLoadBalanceSuggestions(int $schedOpId): array
    {
        $schedOp = ProductionScheduleOperation::findOrFail($schedOpId);
        $tenantId = require_tenant_id();

        // Get alternate machines in the same work center
        $machines = Machine::where('work_center_id', $schedOp->work_center_id)
            ->where('status', 'active')
            ->where('id', '!=', $schedOp->machine_id)
            ->get();

        $suggestions = [];

        foreach ($machines as $m) {
            // Check original slot overlap
            $hasOverlap = ProductionScheduleOperation::where('machine_id', $m->id)
                ->where('planned_start', '<', $schedOp->planned_finish)
                ->where('planned_finish', '>', $schedOp->planned_start)
                ->whereNotIn('status', ['cancelled', 'skipped'])
                ->exists();

            if (!$hasOverlap) {
                $suggestions[] = [
                    'machine' => $m,
                    'suggested_start' => $schedOp->planned_start,
                    'suggested_finish' => $schedOp->planned_finish,
                    'conflict_resolved' => true,
                    'warning' => null,
                ];
            } else {
                // Find next free slot on this machine starting from now
                $searchStart = now();
                $duration = (int) $schedOp->planned_duration_minutes;
                
                $bookings = ProductionScheduleOperation::where('machine_id', $m->id)
                    ->where('planned_finish', '>', $searchStart)
                    ->whereNotIn('status', ['cancelled', 'skipped'])
                    ->orderBy('planned_start')
                    ->get();

                $slotStart = $searchStart->copy();
                $foundSlot = false;

                foreach ($bookings as $b) {
                    if ($slotStart->copy()->addMinutes($duration)->lte($b->planned_start)) {
                        $foundSlot = true;
                        break;
                    }
                    $slotStart = $b->planned_finish->copy();
                }

                if (!$foundSlot) {
                    $slotStart = $bookings->isEmpty() ? $searchStart : $bookings->last()->planned_finish->copy();
                }

                $suggestions[] = [
                    'machine' => $m,
                    'suggested_start' => $slotStart,
                    'suggested_finish' => $slotStart->copy()->addMinutes($duration),
                    'conflict_resolved' => false,
                    'warning' => 'Machine has existing bookings. Placed in next available slot.',
                ];
            }
        }

        return $suggestions;
    }

    private function resolveCalendarForWorkCenter($wc, $tenantId)
    {
        if ($wc->production_calendar_id) {
            $cal = ProductionCalendar::withoutGlobalScopes()->find($wc->production_calendar_id);
            if ($cal) return $cal;
        }
        $default = ProductionCalendar::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();
        if ($default) return $default;

        return new ProductionCalendar([
            'name'         => 'Mon-Fri Fallback Calendar',
            'working_days' => [1, 2, 3, 4, 5],
        ]);
    }

    private function isWorkingDay($calendar, $date, $tenantId): bool
    {
        $dayOfWeek = $date->dayOfWeek;
        $workingDays = $calendar->working_days ?? [1, 2, 3, 4, 5];
        if (!in_array($dayOfWeek, $workingDays)) {
            return false;
        }
        if ($calendar->id) {
            $isHoliday = ProductionCalendarHoliday::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_calendar_id', $calendar->id)
                ->whereDate('holiday_date', $date)
                ->where('active', true)
                ->exists();
            if ($isHoliday) {
                return false;
            }
        }
        return true;
    }
}
