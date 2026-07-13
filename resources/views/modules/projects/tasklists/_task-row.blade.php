@php $canManageTask = auth()->user()->can('update', $task); @endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
    <div @if ($canManageTask) role="button" style="cursor: pointer;"
            onclick="openTaskDetailsDrawer({
                id: {{ $task->id }},
                updateUrl: @js(route('projects.tasks.update', [$project, $task])),
                deleteUrl: @js(route('projects.tasks.destroy', [$project, $task])),
                statusUrl: @js(route('projects.tasks.update-status', [$project, $task])),
                assignUrl: @js(route('projects.tasks.assign', [$project, $task])),
                taskListId: {{ $task->task_list_id }},
                taskListName: @js($task->taskList?->name),
                title: @js($task->title),
                description: @js($task->description),
                assigneeId: @js($task->assignee_id),
                assigneeName: @js($task->assignee?->name),
                reviewerId: @js($task->reviewer_id),
                reviewerName: @js($task->reviewer?->name),
                priority: @js($task->priority),
                status: @js($task->status),
                startDate: @js(optional($task->start_date)->format('Y-m-d')),
                dueDate: @js(optional($task->due_date)->format('Y-m-d')),
                startDateDisplay: @js(optional($task->start_date)->format('d/m/Y')),
                dueDateDisplay: @js(optional($task->due_date)->format('d/m/Y')),
                estimatedHours: @js($task->estimated_hours),
                subtaskStoreUrl: @js(route('projects.tasks.subtasks.store', [$project, $task])),
                dependencyStoreUrl: @js(route('projects.tasks.dependencies.store', [$project, $task])),
                subtasks: @js(\App\Domains\Projects\Support\TaskDrawerPayload::subtasks($project, $task)),
                dependencies: @js(\App\Domains\Projects\Support\TaskDrawerPayload::dependencies($project, $task)),
                otherTasks: @js(\App\Domains\Projects\Support\TaskDrawerPayload::otherTasks($task, $allTasks)),
            })"
        @endif class="d-flex align-items-center gap-2 flex-grow-1 text-truncate" style="min-width: 220px;">
        <span class="fs-11 text-muted font-monospace text-nowrap">{{ $task->task_code }}</span>
        <span class="fw-semibold text-dark fs-13 text-truncate">{{ $task->title }}</span>
    </div>

    <div class="d-flex align-items-center gap-3">
        @if ($task->due_date)
            <span class="fs-11 text-muted text-nowrap"><i class="feather-calendar me-1"></i>{{ $task->due_date->format('d M') }}</span>
        @endif
        <span class="fs-11 text-muted text-nowrap">{{ $task->assignee?->name ?: __('projects.unassigned') }}</span>
        @if ($canManageTask)
            <form method="POST" action="{{ route('projects.tasks.update-status', [$project, $task]) }}">
                @csrf
                @method('PATCH')
                <select name="status" class="form-select form-select-sm fs-11" onchange="this.form.submit()">
                    @foreach (\App\Domains\Projects\Models\Task::STATUSES as $statusOption)
                        <option value="{{ $statusOption }}" @selected($task->status === $statusOption)>
                            {{ __('projects.task_statuses.' . $statusOption) }}
                        </option>
                    @endforeach
                </select>
            </form>
        @else
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
            <x-ui.badge variant="{{ $taskStatusVariant }}" soft class="text-nowrap">
                {{ __('projects.task_statuses.' . $task->status) }}
            </x-ui.badge>
        @endif
    </div>
</div>
