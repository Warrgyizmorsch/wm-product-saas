@props([
    'workOrder',
    'assignments' => null,
])

@php
    $assignmentsList = $assignments ?? $workOrder->assignments;
    if ($assignmentsList instanceof \Illuminate\Database\Eloquent\Collection) {
        $assignmentsList = $assignmentsList->sortBy('assigned_at');
    }
    $assignedCount = $assignmentsList ? $assignmentsList->count() : 0;
    $combinedRate = (float) ($assignmentsList ? $assignmentsList->sum('hourly_rate') : 0.0);

    $isOngoing = $workOrder->status === \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS;

    $combinedHours = (float) ($assignmentsList ? $assignmentsList->sum(function ($assignment) use ($workOrder) {
        if (in_array($workOrder->status, ['completed', 'cancelled'])) {
            return (float) ($assignment->worked_hours ?: 0.0);
        }
        if ($workOrder->status === 'in_progress') {
            $maintStart = $workOrder->actual_start
                ? \Carbon\Carbon::parse($workOrder->actual_start)
                : ($workOrder->planned_start ? \Carbon\Carbon::parse($workOrder->planned_start) : \Carbon\Carbon::parse($workOrder->created_at));
            $assignmentAt = $assignment->assigned_at ? \Carbon\Carbon::parse($assignment->assigned_at) : $maintStart;
            $referenceStart = $assignmentAt->greaterThan($maintStart) ? $assignmentAt : $maintStart;
            $seconds = max(0, $referenceStart->diffInSeconds(now(), false));
            $totalMinutes = (int) round($seconds / 60);
            $h = intdiv($totalMinutes, 60);
            $m = $totalMinutes % 60;
            $accumulated = (float) sprintf('%d.%02d', $h, $m);
            return max((float) ($assignment->worked_hours ?: 0.0), $accumulated);
        }
        return (float) ($assignment->worked_hours ?: 0.0);
    }) : 0.0);
@endphp

<div {{ $attributes->merge(['class' => 'border rounded p-3 bg-light assigned-technicians-summary-card']) }}>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <x-ui.assigned-technicians-trigger :workOrder="$workOrder" :assignments="$assignmentsList" />
                <strong class="text-dark fs-13">
                    {{ $assignedCount }} {{ \Illuminate\Support\Str::plural('Technician', $assignedCount) }} Assigned
                </strong>
            </div>
            <div class="text-muted fs-12">
                Combined Hourly Rate: <strong class="text-dark">{{ format_currency($combinedRate) }}/hr</strong>
            </div>
            @if((float) ($workOrder->mechanic_cost ?? 0) > 0)
                <div class="text-muted fs-12 mt-1">
                    Mechanic Cost: <strong class="text-dark">{{ format_currency($workOrder->mechanic_cost) }}</strong>
                </div>
            @endif
        </div>
        <div class="text-end">
            <span class="badge bg-white text-dark border fs-12 px-2 py-1">
                {{ format_hours($assignmentsList ?? $combinedHours) }}
            </span>
            @if($isOngoing)
                <div class="mt-1">
                    <span class="badge bg-soft-warning text-warning fs-11">Ongoing</span>
                </div>
            @endif
        </div>
    </div>
</div>
