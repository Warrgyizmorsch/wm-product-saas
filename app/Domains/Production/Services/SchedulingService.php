<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SchedulingService
{
    public function __construct(
        private readonly ProductionScheduleNumberService $numberService
    ) {}

    /**
     * Public gateway for generating schedule. Calls forward/backward engines.
     */
    public function generateSchedule(
        ProductionOrder $order,
        Carbon $date,
        string $type = ProductionSchedule::TYPE_FORWARD
    ): ProductionSchedule {
        if ($type === ProductionSchedule::TYPE_FORWARD) {
            return $this->generateForwardSchedule($order, $date);
        }

        if ($type === ProductionSchedule::TYPE_BACKWARD) {
            return $this->generateBackwardSchedule($order, $date);
        }

        throw new \InvalidArgumentException(
            "Scheduling strategy [{$type}] is not supported in this release. Supported: forward, backward."
        );
    }

    /**
     * Generate Forward Schedule: Allocates resources sequentially from a start date.
     */
    public function generateForwardSchedule(ProductionOrder $order, Carbon $startDate): ProductionSchedule
    {
        return DB::transaction(function () use ($order, $startDate) {
            // Delete existing active/draft schedules for this order
            ProductionSchedule::withoutGlobalScopes()
                ->where('production_order_id', $order->id)
                ->whereIn('status', [ProductionSchedule::STATUS_DRAFT, ProductionSchedule::STATUS_SCHEDULED])
                ->delete();

            $tenantId = $order->tenant_id;

            $schedule = ProductionSchedule::create([
                'tenant_id'           => $tenantId,
                'schedule_number'     => $this->numberService->generateNextNumber($tenantId),
                'production_order_id' => $order->id,
                'scheduling_type'     => ProductionSchedule::TYPE_FORWARD,
                'generated_by'        => 'forward',
                'status'              => ProductionSchedule::STATUS_SCHEDULED,
                'scheduled_at'        => now(),
                'created_by'          => auth()->id() ?: 1,
            ]);

            $operations = $order->operations()->orderBy('sequence')->get();
            $cursor     = $startDate->copy();

            foreach ($operations as $op) {
                $times      = $this->calculateOperationTimes($op, $order->quantity_ordered);
                $duration   = $times['total_minutes'];

                // Find optimal machine & slot
                $alloc = $this->findNextAvailableMachine($op->routing_operation_id ?? 0, $cursor, $duration, $tenantId, true);

                $plannedStart  = $alloc['start'] ?? $cursor->copy();
                $plannedFinish = $alloc['finish'] ?? $cursor->copy()->addMinutes((int) ceil($duration));
                $warnings      = $alloc['warnings'] ?? [];
                $machineId     = $alloc['machine_id'] ?? null;
                $priority      = $alloc['priority'] ?? 1;

                ProductionScheduleOperation::create([
                    'tenant_id'                     => $tenantId,
                    'production_schedule_id'        => $schedule->id,
                    'production_order_id'           => $order->id,
                    'production_order_operation_id' => $op->id,
                    'work_center_id'                => $op->work_center_id,
                    'machine_id'                    => $machineId,
                    'sequence'                      => $op->sequence,
                    'priority'                      => $priority,
                    'planned_start'                 => $plannedStart,
                    'planned_finish'                => $plannedFinish,
                    'planned_duration_minutes'      => $duration,
                    'status'                        => $op->sequence === $operations->first()->sequence
                        ? ProductionScheduleOperation::STATUS_READY
                        : ProductionScheduleOperation::STATUS_WAITING,
                    'warnings'                      => $warnings,
                    'locked'                        => false,
                    'lane'                          => 'WorkCenter_' . $op->work_center_id,
                    'resource_id'                   => $machineId ? 'Machine_' . $machineId : 'WorkCenter_' . $op->work_center_id,
                ]);

                // Next operation starts after this one finishes
                $cursor = $plannedFinish->copy();
            }

            // Stash Overall Capacity Utilization
            $schedule->update([
                'capacity_utilization' => $this->calculateOverallUtilization($schedule),
            ]);

            return $schedule;
        });
    }

    /**
     * Generate Backward Schedule: Allocates resources backwards from a due date.
     */
    public function generateBackwardSchedule(ProductionOrder $order, Carbon $dueDate): ProductionSchedule
    {
        return DB::transaction(function () use ($order, $dueDate) {
            ProductionSchedule::withoutGlobalScopes()
                ->where('production_order_id', $order->id)
                ->whereIn('status', [ProductionSchedule::STATUS_DRAFT, ProductionSchedule::STATUS_SCHEDULED])
                ->delete();

            $tenantId = $order->tenant_id;

            $schedule = ProductionSchedule::create([
                'tenant_id'           => $tenantId,
                'schedule_number'     => $this->numberService->generateNextNumber($tenantId),
                'production_order_id' => $order->id,
                'scheduling_type'     => ProductionSchedule::TYPE_BACKWARD,
                'generated_by'        => 'backward',
                'status'              => ProductionSchedule::STATUS_SCHEDULED,
                'scheduled_at'        => now(),
                'created_by'          => auth()->id() ?: 1,
            ]);

            // For backward scheduling, schedule operations in reverse order
            $operations = $order->operations()->orderByDesc('sequence')->get();
            $cursor     = $dueDate->copy();
            $records    = [];

            foreach ($operations as $op) {
                $times    = $this->calculateOperationTimes($op, $order->quantity_ordered);
                $duration = $times['total_minutes'];

                // Find slot searching backwards
                $alloc = $this->findNextAvailableMachine($op->routing_operation_id ?? 0, $cursor, $duration, $tenantId, false);

                $plannedStart  = $alloc['start'] ?? $cursor->copy()->subMinutes((int) ceil($duration));
                $plannedFinish = $alloc['finish'] ?? $cursor->copy();
                $warnings      = $alloc['warnings'] ?? [];
                $machineId     = $alloc['machine_id'] ?? null;
                $priority      = $alloc['priority'] ?? 1;

                $records[] = [
                    'tenant_id'                     => $tenantId,
                    'production_schedule_id'        => $schedule->id,
                    'production_order_id'           => $order->id,
                    'production_order_operation_id' => $op->id,
                    'work_center_id'                => $op->work_center_id,
                    'machine_id'                    => $machineId,
                    'sequence'                      => $op->sequence,
                    'priority'                      => $priority,
                    'planned_start'                 => $plannedStart,
                    'planned_finish'                => $plannedFinish,
                    'planned_duration_minutes'      => $duration,
                    'status'                        => ProductionScheduleOperation::STATUS_WAITING,
                    'warnings'                      => $warnings,
                    'locked'                        => false,
                    'lane'                          => 'WorkCenter_' . $op->work_center_id,
                    'resource_id'                   => $machineId ? 'Machine_' . $machineId : 'WorkCenter_' . $op->work_center_id,
                ];

                // Previous operation in sequence must finish before this one starts
                $cursor = $plannedStart->copy();
            }

            // Write in correct sequence
            usort($records, fn($a, $b) => $a['sequence'] <=> $b['sequence']);
            if (count($records) > 0) {
                $records[0]['status'] = ProductionScheduleOperation::STATUS_READY;
            }

            foreach ($records as $record) {
                ProductionScheduleOperation::create($record);
            }

            $schedule->update([
                'capacity_utilization' => $this->calculateOverallUtilization($schedule),
            ]);

            return $schedule;
        });
    }

    /**
     * Reschedule: Repositions operations from new start date, preserving locked operations.
     */
    public function reschedule(int $scheduleId, Carbon $newStartDate, string $type = 'forward'): ProductionSchedule
    {
        if ($type !== 'forward') {
            throw new \InvalidArgumentException("Rescheduling only supports 'forward' adjustment currently.");
        }

        return DB::transaction(function () use ($scheduleId, $newStartDate) {
            $schedule = ProductionSchedule::findOrFail($scheduleId);
            $ops      = $schedule->operations()->orderBy('sequence')->get();
            $cursor   = $newStartDate->copy();

            foreach ($ops as $op) {
                if ($op->locked) {
                    // Do not move locked operations
                    $cursor = $op->planned_finish->copy();
                    continue;
                }

                // Recalculate optimal slot
                $routingOpId = ProductionOrderOperation::find($op->production_order_operation_id)->routing_operation_id ?? 0;
                $alloc = $this->findNextAvailableMachine($routingOpId, $cursor, $op->planned_duration_minutes, $schedule->tenant_id, true);

                $op->update([
                    'machine_id'     => $alloc['machine_id'] ?? $op->machine_id,
                    'planned_start'  => $alloc['start'] ?? $cursor->copy(),
                    'planned_finish' => $alloc['finish'] ?? $cursor->copy()->addMinutes((int) ceil($op->planned_duration_minutes)),
                    'warnings'       => $alloc['warnings'] ?? [],
                    'priority'       => $alloc['priority'] ?? 1,
                    'resource_id'    => ($alloc['machine_id'] ?? null) ? 'Machine_' . $alloc['machine_id'] : $op->resource_id,
                ]);

                $cursor = $op->planned_finish->copy();
            }

            $schedule->update([
                'generated_by'         => 'reschedule',
                'capacity_utilization' => $this->calculateOverallUtilization($schedule),
            ]);

            return $schedule;
        });
    }

    /**
     * Find best available slot based on calendar, shifts, and machine bookings.
     */
    public function calculateAvailableSlot(
        int $workCenterId,
        ?int $machineId,
        Carbon $from,
        float $durationMinutes,
        bool $forward = true
    ): array {
        $wc = WorkCenter::withoutGlobalScopes()->find($workCenterId);
        if (!$wc) {
            return ['start' => $from->copy(), 'finish' => $from->copy()->addMinutes((int)$durationMinutes), 'warnings' => []];
        }

        $tenantId = $wc->tenant_id;
        $warnings = [];

        // 1. Resolve Calendar Fallback Hierarchy
        $calendar = $this->resolveCalendar($wc, $tenantId);

        // Fetch shifts assigned to work center
        $shifts = $wc->shifts()->where('active', true)->get();
        if ($shifts->isEmpty()) {
            // Standard 8-hour shift fallback (480 minutes) if no shifts configured
            $shifts = collect([
                new ProductionShift([
                    'name'          => 'Standard Shift',
                    'code'          => 'STD',
                    'start_time'    => '08:00:00',
                    'end_time'      => '16:00:00',
                    'break_minutes' => 0,
                ])
            ]);
        }

        // Fetch other active operations on this resource to avoid overlap
        $bookingsQuery = ProductionScheduleOperation::withoutGlobalScopes()
            ->whereNotIn('status', [
                ProductionScheduleOperation::STATUS_COMPLETED,
                ProductionScheduleOperation::STATUS_CANCELLED,
                ProductionScheduleOperation::STATUS_SKIPPED,
            ]);

        if ($machineId) {
            $bookingsQuery->where('machine_id', $machineId);
        } else {
            $bookingsQuery->where('work_center_id', $workCenterId)->whereNull('machine_id');
        }

        $bookings = $bookingsQuery->get();

        $searchDate = $from->copy();
        $limitDays  = 365;

        for ($day = 0; $day < $limitDays; $day++) {
            // Check calendar working days & holidays
            if (!$this->isWorkingDay($calendar, $searchDate, $tenantId)) {
                $warnings[] = [
                    'code'     => 'HOLIDAY_SKIPPED',
                    'message'  => "Scheduled date {$searchDate->toDateString()} skipped due to holiday/weekend configuration.",
                    'severity' => 'info',
                ];
                $searchDate->addDay()->startOfDay();
                continue;
            }

            // Build work windows from shifts
            $windows = [];
            foreach ($shifts as $shift) {
                $startStr = $searchDate->toDateString() . ' ' . $shift->start_time;
                $endStr   = $searchDate->toDateString() . ' ' . $shift->end_time;

                $start = Carbon::parse($startStr);
                $end   = Carbon::parse($endStr);

                if ($end->lt($start)) {
                    // Overlapping midnight shift
                    $end->addDay();
                }

                // Deduct breaks
                if ($shift->break_minutes > 0) {
                    $end->subMinutes($shift->break_minutes);
                }

                $windows[] = ['start' => $start, 'finish' => $end];
            }

            // Sort windows
            usort($windows, fn($a, $b) => $a['start'] <=> $b['start']);

            // Find free space within work windows
            foreach ($windows as $window) {
                $searchStart  = $forward ? $window['start']->max($from) : $window['start'];
                $searchFinish = $window['finish'];

                if ($searchStart->gt($searchFinish) || $searchStart->diffInMinutes($searchFinish) < $durationMinutes) {
                    continue;
                }

                // Check overlays with other bookings inside this window
                $windowBookings = $bookings->filter(fn($b) => 
                    $b->planned_start->lt($searchFinish) && $b->planned_finish->gt($searchStart)
                )->sortBy('planned_start');

                $slotCandidate = $searchStart->copy();
                
                while ($slotCandidate->copy()->addMinutes((int)$durationMinutes)->lte($searchFinish)) {
                    $candidateEnd = $slotCandidate->copy()->addMinutes((int)$durationMinutes);
                    $overlap      = false;

                    foreach ($windowBookings as $b) {
                        if ($b->planned_start->lt($candidateEnd) && $b->planned_finish->gt($slotCandidate)) {
                            // Overlap! Push candidate past this booking
                            $slotCandidate = $b->planned_finish->copy();
                            $overlap       = true;
                            break;
                        }
                    }

                    if (!$overlap) {
                        // Found a valid finite capacity slot
                        return [
                            'start'    => $slotCandidate,
                            'finish'   => $candidateEnd,
                            'warnings' => $warnings,
                        ];
                    }
                }
            }

            $searchDate->addDay()->startOfDay();
        }

        // Full fallback: Unlimited capacity schedule at start if no slot resolved in 365 days
        return [
            'start'    => $from->copy(),
            'finish'   => $from->copy()->addMinutes((int)$durationMinutes),
            'warnings' => array_merge($warnings, [[
                'code'     => 'CAPACITY_OVERLOAD',
                'message'  => 'No finite slot found. Scheduled with standard unlimited capacity.',
                'severity' => 'warning',
            ]]),
        ];
    }

    /**
     * Resolves alternate machines and picks the one with the earliest available slot.
     */
    public function findNextAvailableMachine(
        int $routingOpId,
        Carbon $from,
        float $durationMinutes,
        int $tenantId,
        bool $forward = true
    ): array {
        $warnings = [];
        $routingOp = RoutingOperation::withoutGlobalScopes()->find($routingOpId);

        if (!$routingOp) {
            return [
                'machine_id' => null,
                'start'      => $from->copy(),
                'finish'     => $from->copy()->addMinutes((int)$durationMinutes),
                'warnings'   => [],
                'priority'   => 1,
            ];
        }

        $candidates = [];

        // 1. Evaluate Primary Machine
        if ($routingOp->machine_id) {
            $machine = Machine::withoutGlobalScopes()->find($routingOp->machine_id);
            if ($machine) {
                $validation = $this->validateMachineForScheduling($machine, $routingOp->work_center_id, $tenantId);
                if ($validation['valid']) {
                    $candidates[] = [
                        'machine'  => $machine,
                        'priority' => 0, // Priority 0 is primary
                    ];
                } else {
                    $warnings[] = $validation['warning'];
                }
            }
        }

        // 2. Evaluate Alternates
        $alternates = RoutingOperationAlternateMachine::where('routing_operation_id', $routingOp->id)->get();
        foreach ($alternates as $alt) {
            $altMachine = Machine::withoutGlobalScopes()->find($alt->machine_id);
            if ($altMachine) {
                $validation = $this->validateMachineForScheduling($altMachine, $routingOp->work_center_id, $tenantId);
                if ($validation['valid']) {
                    $candidates[] = [
                        'machine'  => $altMachine,
                        'priority' => $alt->priority,
                    ];
                } else {
                    $warnings[] = $validation['warning'];
                }
            }
        }

        if (empty($candidates)) {
            $warnings[] = [
                'code'     => 'NO_AVAILABLE_MACHINE',
                'message'  => 'No valid active machines configured for this routing operation.',
                'severity' => 'warning',
            ];

            return [
                'machine_id' => $routingOp->machine_id,
                'start'      => $from->copy(),
                'finish'     => $from->copy()->addMinutes((int)$durationMinutes),
                'warnings'   => $warnings,
                'priority'   => 1,
            ];
        }

        // Calculate slot for each valid candidate
        $evaluated = [];
        foreach ($candidates as $cand) {
            $slot = $this->calculateAvailableSlot($routingOp->work_center_id, $cand['machine']->id, $from, $durationMinutes, $forward);
            $evaluated[] = [
                'machine'  => $cand['machine'],
                'priority' => $cand['priority'],
                'slot'     => $slot,
            ];
        }

        // Sort candidates: earliest start slot first, priority as tie-breaker
        usort($evaluated, function ($a, $b) {
            $startA = $a['slot']['start'];
            $startB = $b['slot']['start'];
            if ($startA->eq($startB)) {
                return $a['priority'] <=> $b['priority'];
            }
            return $startA <=> $startB;
        });

        $winner = $evaluated[0];
        $allWarnings = array_merge($warnings, $winner['slot']['warnings']);

        // Warn if an alternate machine was selected over the primary
        if ($winner['priority'] > 0) {
            $allWarnings[] = [
                'code'     => 'ALTERNATE_MACHINE_USED',
                'message'  => "Primary machine was bypassed. Alternate machine [{$winner['machine']->name}] assigned.",
                'severity' => 'info',
            ];
        }

        return [
            'machine_id' => $winner['machine']->id,
            'start'      => $winner['slot']['start'],
            'finish'     => $winner['slot']['finish'],
            'warnings'   => $allWarnings,
            'priority'   => $winner['priority'] === 0 ? 1 : $winner['priority'] + 1,
        ];
    }

    /**
     * Resolve working hours of Work Center in minutes.
     */
    public function calculateCapacity(int $workCenterId, Carbon $date): float
    {
        $wc = WorkCenter::withoutGlobalScopes()->find($workCenterId);
        if (!$wc || !$wc->isActive()) {
            return 0.0;
        }

        $calendar = $this->resolveCalendar($wc, $wc->tenant_id);
        if (!$this->isWorkingDay($calendar, $date, $wc->tenant_id)) {
            return 0.0;
        }

        $shifts = $wc->shifts()->where('active', true)->get();
        if ($shifts->isEmpty()) {
            // Standard shift default (480 minutes)
            return 480.0 * (($wc->efficiency_percentage ?? 100.0) / 100.0);
        }

        $totalMinutes = 0.0;
        foreach ($shifts as $shift) {
            $start = Carbon::parse($shift->start_time);
            $end   = Carbon::parse($shift->end_time);

            if ($end->lt($start)) {
                $end->addDay();
            }

            $diff = $start->diffInMinutes($end);
            if ($shift->break_minutes > 0) {
                $diff -= $shift->break_minutes;
            }

            $totalMinutes += max(0.0, $diff);
        }

        return $totalMinutes * (($wc->efficiency_percentage ?? 100.0) / 100.0);
    }

    /**
     * Conflict detector.
     */
    public function detectConflicts(int $tenantId): array
    {
        $conflicts = [];
        $ops = ProductionScheduleOperation::withoutGlobalScopes()
            ->whereHas('schedule', fn($q) => $q->where('tenant_id', $tenantId)->whereIn('status', ['scheduled', 'released', 'in_progress']))
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->whereNotNull('machine_id')
            ->orderBy('machine_id')
            ->orderBy('planned_start')
            ->get();

        $grouped = $ops->groupBy('machine_id');
        foreach ($grouped as $machineId => $machineOps) {
            $machineOps = $machineOps->values();
            for ($i = 0; $i < $machineOps->count() - 1; $i++) {
                $curr = $machineOps[$i];
                $next = $machineOps[$i+1];

                if ($curr->planned_finish->gt($next->planned_start)) {
                    $conflicts[] = "Overlap on machine #{$machineId}: Schedule Op #{$curr->id} overlaps with Op #{$next->id}.";
                }
            }
        }

        return $conflicts;
    }

    /**
     * Overload detector.
     */
    public function detectOverloads(int $tenantId): array
    {
        $overloads = [];
        $ops = ProductionScheduleOperation::withoutGlobalScopes()
            ->whereHas('schedule', fn($q) => $q->where('tenant_id', $tenantId)->whereIn('status', ['scheduled', 'released', 'in_progress']))
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->get();

        $grouped = $ops->groupBy(fn($op) => $op->work_center_id . '_' . $op->planned_start->toDateString());
        foreach ($grouped as $key => $wcOps) {
            [$wcId, $dateStr] = explode('_', $key);
            $date = Carbon::parse($dateStr);
            $capacity = $this->calculateCapacity((int)$wcId, $date);

            $scheduledMinutes = $wcOps->sum('planned_duration_minutes');
            if ($scheduledMinutes > $capacity) {
                $wc = WorkCenter::withoutGlobalScopes()->find($wcId);
                $overloads[] = "Work Center [{$wc->name}] overloaded on {$dateStr}: Scheduled {$scheduledMinutes} minutes, Capacity is {$capacity} minutes.";
            }
        }

        return $overloads;
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function calculateOperationTimes(ProductionOrderOperation $op, float $quantity): array
    {
        $setup = (float)$op->setup_time_planned;
        $proc  = (float)$op->processing_time_planned * $quantity;
        return [
            'setup_minutes'      => $setup,
            'processing_minutes' => $proc,
            'total_minutes'      => $setup + $proc,
        ];
    }

    private function resolveCalendar(WorkCenter $wc, int $tenantId): ProductionCalendar
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

        // Mon-Fri virtual fallback
        return new ProductionCalendar([
            'name'         => 'Mon-Fri Fallback Calendar',
            'working_days' => [1, 2, 3, 4, 5],
        ]);
    }

    private function isWorkingDay(ProductionCalendar $calendar, Carbon $date, int $tenantId): bool
    {
        $dayOfWeek = $date->dayOfWeek;
        $workingDays = $calendar->working_days ?? [1, 2, 3, 4, 5];
        if (!in_array($dayOfWeek, $workingDays)) {
            return false;
        }

        if ($calendar->id) {
            $isHoliday = ProductionCalendarHoliday::withoutGlobalScopes()
                ->where('production_calendar_id', $calendar->id)
                ->whereDate('holiday_date', $date)
                ->exists();

            if ($isHoliday) {
                return false;
            }
        }

        return true;
    }

    private function validateMachineForScheduling(Machine $machine, int $workCenterId, int $tenantId): array
    {
        if ($machine->tenant_id !== $tenantId) {
            return [
                'valid'   => false,
                'warning' => [
                    'code'     => 'MACHINE_UNAVAILABLE',
                    'message'  => "Machine [{$machine->name}] does not belong to this tenant.",
                    'severity' => 'warning',
                ],
            ];
        }

        if ($machine->work_center_id !== $workCenterId) {
            return [
                'valid'   => false,
                'warning' => [
                    'code'     => 'MACHINE_UNAVAILABLE',
                    'message'  => "Machine [{$machine->name}] does not belong to Work Center #{$workCenterId}.",
                    'severity' => 'warning',
                ],
            ];
        }

        if (!$machine->isActive()) {
            return [
                'valid'   => false,
                'warning' => [
                    'code'     => 'MACHINE_UNAVAILABLE',
                    'message'  => "Machine [{$machine->name}] is not active (status: {$machine->status}).",
                    'severity' => 'warning',
                ],
            ];
        }

        if ($machine->isUnderMaintenance()) {
            return [
                'valid'   => false,
                'warning' => [
                    'code'     => 'MACHINE_UNDER_MAINTENANCE',
                    'message'  => "Machine [{$machine->name}] is under maintenance.",
                    'severity' => 'warning',
                ],
            ];
        }

        if ($machine->isDecommissioned() || $machine->isInactive()) {
            return [
                'valid'   => false,
                'warning' => [
                    'code'     => 'MACHINE_UNAVAILABLE',
                    'message'  => "Machine [{$machine->name}] is decommissioned or inactive.",
                    'severity' => 'warning',
                ],
            ];
        }

        return ['valid' => true];
    }

    private function calculateOverallUtilization(ProductionSchedule $schedule): float
    {
        $ops = $schedule->operations;
        if ($ops->isEmpty()) return 0.00;

        $totalScheduled = $ops->sum('planned_duration_minutes');
        
        // Sum total daily capacities for the scheduled dates
        $workCenterIds = $ops->pluck('work_center_id')->unique();
        $minDate = $ops->min('planned_start');
        $maxDate = $ops->max('planned_finish');

        if (!$minDate || !$maxDate) return 0.00;

        $totalCapacity = 0.0;
        $date = $minDate->copy()->startOfDay();
        
        while ($date->lte($maxDate)) {
            foreach ($workCenterIds as $wcId) {
                $totalCapacity += $this->calculateCapacity($wcId, $date);
            }
            $date->addDay();
        }

        if ($totalCapacity <= 0.0) return 100.00;

        return (float)round(min(100.00, ($totalScheduled / $totalCapacity) * 100.00), 2);
    }

    /**
     * Validate a generated schedule.
     *
     * @throws \LogicException on the first validation failure found.
     */
    public function validateSchedule(ProductionSchedule $schedule): void
    {
        $tenantId = $schedule->order->tenant_id;

        foreach ($schedule->operations()->with(['workCenter', 'machine'])->get() as $op) {
            if (!$op->workCenter || !$op->workCenter->isActive()) {
                throw new \LogicException(
                    "Work Center for operation [{$op->sequence}] is inactive or missing."
                );
            }

            if ($op->machine_id && $op->machine) {
                if ($op->machine->tenant_id !== $tenantId) {
                    throw new \LogicException(
                        "Machine [{$op->machine->name}] does not belong to this tenant."
                    );
                }
                if ($op->machine->work_center_id !== $op->work_center_id) {
                    throw new \LogicException(
                        "Machine [{$op->machine->name}] does not belong to Work Center [{$op->workCenter->name}]."
                    );
                }
                if (!$op->machine->isActive()) {
                    throw new \LogicException(
                        "Machine [{$op->machine->name}] is not active (status: {$op->machine->status})."
                    );
                }
            }
        }
    }
}
