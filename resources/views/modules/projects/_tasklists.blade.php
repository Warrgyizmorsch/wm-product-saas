<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fw-bold text-dark">
            <i class="feather-list me-2 text-primary"></i>{{ __('projects.tasklists') }}
        </h5>
        @if ($canManageTaskLists)
            <button type="button" class="btn btn-primary btn-sm" onclick="openTaskListDrawer('add')">
                <i class="feather-plus me-1"></i>{{ __('projects.add_tasklist') }}
            </button>
        @endif
    </div>
    <div class="card-body p-0">
        <x-ui.table>
            <thead>
                <tr>
                    <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.tasklist_order') }}</th>
                    <th scope="col">{{ __('projects.tasklist_name') }}</th>
                    <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.milestone') }}</th>
                    <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.tasklist_owner') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($taskLists as $index => $taskList)
                    <tr>
                        <td>
                            @if ($canManageTaskLists)
                                <div class="d-flex flex-column gap-1">
                                    <form method="POST" action="{{ route('projects.tasklists.move-up', [$project, $taskList]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-light btn-sm p-1" title="{{ __('projects.move_up') }}" @disabled($index === 0)>
                                            <i class="feather-chevron-up"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('projects.tasklists.move-down', [$project, $taskList]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-light btn-sm p-1" title="{{ __('projects.move_down') }}" @disabled($index === $taskLists->count() - 1)>
                                            <i class="feather-chevron-down"></i>
                                        </button>
                                    </form>
                                </div>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </td>
                        <td @if ($canManageTaskLists) role="button" style="cursor: pointer;"
                                onclick="openTaskListDrawer('edit', {
                                    id: {{ $taskList->id }},
                                    updateUrl: @js(route('projects.tasklists.update', [$project, $taskList])),
                                    deleteUrl: @js(route('projects.tasklists.destroy', [$project, $taskList])),
                                    name: @js($taskList->name),
                                    description: @js($taskList->description),
                                    ownerId: @js($taskList->owner_id),
                                    milestoneId: @js($taskList->milestone_id)
                                })"
                            @endif>
                            <div class="fw-semibold text-dark">{{ $taskList->name }}</div>
                            @if ($taskList->description)
                                <div class="fs-11 text-muted">{{ \Illuminate\Support\Str::limit($taskList->description, 60) }}</div>
                            @endif
                        </td>
                        <td>{{ $taskList->milestone?->name ?: '—' }}</td>
                        <td>{{ $taskList->owner?->name ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td colspan="3" class="pb-3">
                            @php $listTasks = $tasksByList->get($taskList->id, collect()); @endphp
                            <div class="border rounded p-2 bg-light bg-opacity-50">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-11 fw-semibold text-uppercase text-muted">{{ __('projects.tasks') }}</span>
                                    @if ($canCreateTasks)
                                        <button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="openTaskDrawer('add', { taskListId: {{ $taskList->id }} })">
                                            <i class="feather-plus me-1"></i>{{ __('projects.add_task') }}
                                        </button>
                                    @endif
                                </div>
                                @forelse ($listTasks as $task)
                                    @php $canManageTask = auth()->user()->can('update', $task); @endphp
                                    <div class="d-flex align-items-center justify-content-between border-top py-2">
                                        <div @if ($canManageTask) role="button" style="cursor: pointer;"
                                                onclick="openTaskDrawer('edit', {
                                                    id: {{ $task->id }},
                                                    updateUrl: @js(route('projects.tasks.update', [$project, $task])),
                                                    deleteUrl: @js(route('projects.tasks.destroy', [$project, $task])),
                                                    statusUrl: @js(route('projects.tasks.update-status', [$project, $task])),
                                                    assignUrl: @js(route('projects.tasks.assign', [$project, $task])),
                                                    taskListId: {{ $task->task_list_id }},
                                                    title: @js($task->title),
                                                    description: @js($task->description),
                                                    assigneeId: @js($task->assignee_id),
                                                    reviewerId: @js($task->reviewer_id),
                                                    priority: @js($task->priority),
                                                    status: @js($task->status),
                                                    startDate: @js(optional($task->start_date)->format('Y-m-d')),
                                                    dueDate: @js(optional($task->due_date)->format('Y-m-d')),
                                                    estimatedHours: @js($task->estimated_hours),
                                                })"
                                            @endif>
                                            <div class="fw-semibold text-dark fs-13">{{ $task->task_code }} — {{ $task->title }}</div>
                                            <div class="fs-11 text-muted">
                                                {{ __('projects.task_assignee') }}: {{ $task->assignee?->name ?: __('projects.unassigned') }}
                                            </div>
                                        </div>
                                        @if ($canManageTask)
                                            <form method="POST" action="{{ route('projects.tasks.update-status', [$project, $task]) }}" class="ms-2">
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
                                            <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold text-nowrap">
                                                {{ __('projects.task_statuses.' . $task->status) }}
                                            </span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center text-muted fs-12 py-2">{{ __('projects.no_tasks') }}</div>
                                @endforelse
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            {{ __('projects.no_tasklists') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>
</div>

@if ($canManageTaskLists)
    @include('modules.projects.tasklists._drawer')

    @if ($errors->any() && in_array(old('_tasklist_form'), ['add', 'edit'], true))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    @if (old('_tasklist_form') === 'edit')
                        openTaskListDrawer('edit', {
                            id: {{ (int) old('_tasklist_id') }},
                            updateUrl: @js(route('projects.tasklists.update', [$project, (int) old('_tasklist_id')])),
                            deleteUrl: @js(route('projects.tasklists.destroy', [$project, (int) old('_tasklist_id')])),
                            name: @js(old('name')),
                            description: @js(old('description')),
                            ownerId: @js(old('owner_id')),
                            milestoneId: @js(old('milestone_id')),
                        });
                    @else
                        openTaskListDrawer('add', {
                            name: @js(old('name')),
                            description: @js(old('description')),
                            ownerId: @js(old('owner_id')),
                            milestoneId: @js(old('milestone_id')),
                        });
                    @endif
                });
            </script>
        @endpush
    @endif
@endif

@if ($canCreateTasks)
    @include('modules.projects.tasks._drawer')

    @if ($errors->any() && in_array(old('_task_form'), ['add', 'edit'], true))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    @if (old('_task_form') === 'edit')
                        openTaskDrawer('edit', {
                            id: {{ (int) old('_task_id') }},
                            updateUrl: @js(route('projects.tasks.update', [$project, (int) old('_task_id')])),
                            deleteUrl: @js(route('projects.tasks.destroy', [$project, (int) old('_task_id')])),
                            statusUrl: @js(route('projects.tasks.update-status', [$project, (int) old('_task_id')])),
                            assignUrl: @js(route('projects.tasks.assign', [$project, (int) old('_task_id')])),
                            taskListId: @js(old('task_list_id')),
                            title: @js(old('title')),
                            description: @js(old('description')),
                            assigneeId: @js(old('assignee_id')),
                            reviewerId: @js(old('reviewer_id')),
                            priority: @js(old('priority')),
                            startDate: @js(old('start_date')),
                            dueDate: @js(old('due_date')),
                            estimatedHours: @js(old('estimated_hours')),
                        });
                    @else
                        openTaskDrawer('add', {
                            taskListId: @js(old('task_list_id')),
                            title: @js(old('title')),
                            description: @js(old('description')),
                            assigneeId: @js(old('assignee_id')),
                            reviewerId: @js(old('reviewer_id')),
                            priority: @js(old('priority')),
                            startDate: @js(old('start_date')),
                            dueDate: @js(old('due_date')),
                            estimatedHours: @js(old('estimated_hours')),
                        });
                    @endif
                });
            </script>
        @endpush
    @endif
@endif
