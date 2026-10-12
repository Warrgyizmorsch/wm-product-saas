@props([
    'workOrder',
    'assignments' => null,
    'size' => null,
])

@php
    $assignmentsList = $assignments ?? $workOrder->assignments;
    if ($assignmentsList instanceof \Illuminate\Database\Eloquent\Collection) {
        $assignmentsList = $assignmentsList->sortBy('assigned_at');
    }
    $assignedCount = $assignmentsList ? $assignmentsList->count() : 0;

    $technicianNames = $assignmentsList ? $assignmentsList->map(function ($assignment) {
        return $assignment->assignment_type === 'internal'
            ? ($assignment->technician?->name ?? $assignment->technician_name)
            : ($assignment->technician_name ?: 'External technician');
    })->filter()->values() : collect();

    $summaryText = $technicianNames->isNotEmpty()
        ? $technicianNames->implode(', ')
        : 'No assigned technicians yet';

    $paddingClass = $size === 'xs' ? 'py-0 px-2' : 'py-1 px-2';
    $fontSizeClass = $size === 'xs' ? 'fs-11' : 'fs-12';
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => "btn btn-sm btn-light border rounded-2 d-inline-flex align-items-center gap-1 {$paddingClass} text-dark assigned-technicians-trigger"]) }}
    data-bs-toggle="modal"
    data-bs-target="#assignedTechniciansModal"
    aria-label="Assigned technicians: {{ $summaryText }}"
>
    <i class="feather-users {{ $fontSizeClass }} text-muted"></i>
    <span class="fw-bold {{ $fontSizeClass }}">{{ $assignedCount }}</span>
</button>
