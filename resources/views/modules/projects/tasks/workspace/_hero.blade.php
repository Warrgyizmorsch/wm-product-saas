@php
    $heroPriorityOptions = collect(\App\Domains\Projects\Models\Task::PRIORITIES)
        ->mapWithKeys(fn ($priority) => [$priority => __('projects.priorities.' . $priority)]);

    $heroCloneJsData = [
        'taskListId' => $task->task_list_id,
        'title' => $task->title ? $task->title . ' ' . __('projects.clone_suffix') : '',
        'description' => $task->description,
        'assigneeId' => $task->assignee_id,
        'reviewerId' => $task->reviewer_id,
        'priority' => $task->priority,
        'startDate' => optional($task->start_date)->format('Y-m-d'),
        'dueDate' => optional($task->due_date)->format('Y-m-d'),
        'estimatedHours' => $task->estimated_hours,
    ];
@endphp

<a href="{{ $backUrl }}" class="fs-12 text-muted text-decoration-none mb-2 d-inline-flex align-items-center">
    <i class="feather-arrow-left me-1"></i>{{ __('projects.back_to_tasklist') }}
</a>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
    <div class="flex-grow-1" style="min-width: 0;">
        <div class="fs-11 text-uppercase text-muted fw-bold font-monospace mb-1">{{ $task->task_code }}</div>
        <h4 class="fw-bold text-dark mb-0">
            @if ($canManageTask)
                <x-ui.inline-edit field="title" :value="$task->title" :url="route('projects.tasks.field', [$project, $task])" :label="__('projects.task_title')" />
            @else
                {{ $task->title }}
            @endif
        </h4>
    </div>

    @if ($canManageTask)
        <div class="flex-shrink-0">
            <x-ui.action-dropdown id="taskWorkspaceActions">
                <li>
                    <a class="dropdown-item" href="javascript:void(0);" onclick="openTaskModal('add', @js($heroCloneJsData))">
                        <i class="feather-copy me-2"></i>{{ __('projects.clone') }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item text-danger" href="javascript:void(0);"
                       onclick="confirmAction(@js(__('projects.confirm_remove_task')), function () { document.getElementById('taskWorkspaceDeleteForm').submit(); })">
                        <i class="feather-trash-2 me-2"></i>{{ __('projects.remove') }}
                    </a>
                </li>
            </x-ui.action-dropdown>
            <form id="taskWorkspaceDeleteForm" method="POST" action="{{ route('projects.tasks.destroy', [$project, $task]) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        </div>
    @endif
</div>

<div class="d-flex flex-wrap align-items-center gap-3">
    <div>
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.status') }}</div>
        @if ($canManageTask && $legalNextStatuses !== [])
            <form method="POST" action="{{ route('projects.tasks.update-status', [$project, $task]) }}">
                @csrf
                @method('PATCH')
                <select name="status" class="form-select form-select-sm fs-12" onchange="this.form.submit()">
                    <option value="{{ $task->status }}" selected>{{ __('projects.task_statuses.' . $task->status) }}</option>
                    @foreach ($legalNextStatuses as $nextStatus)
                        <option value="{{ $nextStatus }}">{{ __('projects.task_statuses.' . $nextStatus) }}</option>
                    @endforeach
                </select>
            </form>
        @else
            @php
                $heroStatusVariant = match ($task->status) {
                    'In Progress' => 'info',
                    'Review' => 'warning',
                    'On Hold' => 'dark',
                    'Completed' => 'success',
                    'Cancelled' => 'danger',
                    default => 'secondary',
                };
            @endphp
            <x-ui.badge variant="{{ $heroStatusVariant }}" soft>{{ __('projects.task_statuses.' . $task->status) }}</x-ui.badge>
        @endif
    </div>

    <div>
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.priority') }}</div>
        @if ($canManageTask)
            <x-ui.inline-edit field="priority" :value="$task->priority" :url="route('projects.tasks.field', [$project, $task])" type="select" :options="$heroPriorityOptions" :label="__('projects.priority')" />
        @else
            {{ __('projects.priorities.' . $task->priority) }}
        @endif
    </div>
</div>
