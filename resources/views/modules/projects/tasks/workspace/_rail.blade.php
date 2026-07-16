@php
    if ($canManageTask) {
        $assigneeOptions = $activeMembers->pluck('user.name', 'user_id')->prepend(__('projects.unassigned'), '');
        $reviewerOptions = $assigneeOptions;
    }
@endphp

<div class="border rounded-3 p-3">
    <h6 class="fw-bold text-dark mb-3">{{ __('projects.details') }}</h6>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_assignee') }}</div>
        @if ($canManageTask)
            <x-ui.inline-edit field="assignee_id" :value="$task->assignee_id" :url="route('projects.tasks.field', [$project, $task])" type="select2" :options="$assigneeOptions" :label="__('projects.task_assignee')" />
        @else
            <div class="fs-13 text-dark">{{ $task->assignee?->name ?: __('projects.unassigned') }}</div>
        @endif
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_reviewer') }}</div>
        @if ($canManageTask)
            <x-ui.inline-edit field="reviewer_id" :value="$task->reviewer_id" :url="route('projects.tasks.field', [$project, $task])" type="select2" :options="$reviewerOptions" :label="__('projects.task_reviewer')" />
        @else
            <div class="fs-13 text-dark">{{ $task->reviewer?->name ?: __('projects.unassigned') }}</div>
        @endif
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.start_date') }}</div>
            <div class="fs-13 text-dark">{{ $task->start_date?->format('d/m/Y') ?: '—' }}</div>
        </div>
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.due_date') }}</div>
            @if ($canManageTask)
                <x-ui.inline-edit field="due_date" :value="optional($task->due_date)->format('Y-m-d')" :url="route('projects.tasks.field', [$project, $task])" type="date" :label="__('projects.due_date')" />
            @else
                <div class="fs-13 text-dark">{{ $task->due_date?->format('d/m/Y') ?: '—' }}</div>
            @endif
        </div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.estimated_hours') }}</div>
        <div class="fs-13 text-dark">{{ $task->estimated_hours ?? '—' }}</div>
    </div>
    {{-- Future extension point: Logged/Actual Hours will render here once
         Time Logs ship — intentionally omitted for now since nothing writes
         actual_hours yet. --}}

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_list') }}</div>
        <div class="fs-13 text-dark">{{ $task->taskList?->name ?: '—' }}</div>
    </div>

    <div>
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.milestone') }}</div>
        <div class="fs-13 text-dark">{{ $task->milestone?->name ?: '—' }}</div>
    </div>
</div>
