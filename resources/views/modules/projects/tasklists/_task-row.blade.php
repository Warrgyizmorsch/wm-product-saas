@php
    $taskStatusVariant = match ($task->status) {
        'In Progress' => 'info',
        'Review' => 'warning',
        'On Hold' => 'dark',
        'Completed' => 'success',
        'Cancelled' => 'danger',
        default => 'secondary',
    };
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
    <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="d-flex align-items-center gap-2 flex-grow-1 text-truncate text-decoration-none" style="min-width: 220px;">
        <span class="fs-11 text-muted font-monospace text-nowrap">{{ $task->task_code }}</span>
        <span class="fw-semibold text-dark fs-13 text-truncate">{{ $task->title }}</span>
    </a>

    <div class="d-flex align-items-center gap-3">
        @if ($task->due_date)
            <span class="fs-11 text-muted text-nowrap"><i class="feather-calendar me-1"></i>{{ $task->due_date->format('d M') }}</span>
        @endif
        <span class="fs-11 text-muted text-nowrap">{{ $task->assignee?->name ?: __('projects.unassigned') }}</span>
        <x-ui.badge variant="{{ $taskStatusVariant }}" soft class="text-nowrap">
            {{ __('projects.task_statuses.' . $task->status) }}
        </x-ui.badge>
    </div>
</div>
