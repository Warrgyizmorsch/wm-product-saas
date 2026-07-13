@php
    $listTasks = $tasksByList->get($taskList->id, collect());
    $listStats = $dashboard['task_lists'][$taskList->id] ?? ['total' => 0, 'done' => 0, 'percent' => 0];
    $taskListBodyId = 'taskListBody' . $taskList->id;
@endphp

<div class="border rounded-3 task-list-card {{ !$loop->last ? 'mb-3' : '' }}" data-task-list-card data-task-list-id="{{ $taskList->id }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 {{ $listTasks->isNotEmpty() ? 'border-bottom' : '' }}">
        <div class="d-flex align-items-start gap-2 flex-grow-1" style="min-width: 220px;">
            <button type="button" class="btn btn-light btn-sm p-1 task-list-toggle" data-task-list-toggle
                    data-bs-toggle="collapse" data-bs-target="#{{ $taskListBodyId }}"
                    aria-expanded="true" aria-controls="{{ $taskListBodyId }}"
                    title="{{ __('projects.expand_collapse') }}">
                <i class="feather-chevron-down task-list-toggle-icon"></i>
            </button>
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div @if ($canManageTaskLists) role="button" style="cursor: pointer;"
                            onclick="openTaskListDetailsDrawer({
                                id: {{ $taskList->id }},
                                updateUrl: @js(route('projects.tasklists.update', [$project, $taskList])),
                                deleteUrl: @js(route('projects.tasklists.destroy', [$project, $taskList])),
                                name: @js($taskList->name),
                                description: @js($taskList->description),
                                ownerId: @js($taskList->owner_id),
                                ownerName: @js($taskList->owner?->name),
                                milestoneId: @js($taskList->milestone_id),
                                milestoneName: @js($taskList->milestone?->name)
                            })"
                        @endif class="fw-semibold text-dark fs-14">
                        {{ $taskList->name }}
                    </div>
                    <span class="fs-11 text-muted">({{ $listTasks->count() }})</span>
                    @if ($taskList->milestone)
                        <x-ui.badge variant="info" soft class="fs-10">
                            <i class="feather-flag me-1"></i>{{ $taskList->milestone->name }}
                        </x-ui.badge>
                    @endif
                </div>
                @if ($taskList->owner)
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1 fs-11 text-muted">
                        <span><i class="feather-user me-1"></i>{{ $taskList->owner->name }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            @if ($listStats['total'] > 0)
                <div style="min-width: 130px;" class="d-none d-md-block">
                    <div class="d-flex justify-content-between fs-11 text-muted mb-1">
                        <span>{{ $listStats['done'] }} / {{ $listStats['total'] }}</span>
                        <span class="fw-semibold">{{ $listStats['percent'] }}%</span>
                    </div>
                    <div class="progress ht-6">
                        <div class="progress-bar bg-success" style="width: {{ $listStats['percent'] }}%"></div>
                    </div>
                </div>
            @endif

            @if ($canCreateTasks)
                <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" onclick="openTaskModal('add', { taskListId: {{ $taskList->id }} })">
                    <i class="feather-plus me-1"></i>{{ __('projects.add_task') }}
                </button>
            @endif

            @if ($canManageTaskLists)
                <div class="d-flex gap-1">
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

                <x-ui.action-dropdown id="taskListActions{{ $taskList->id }}">
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="openTaskListModal('edit', {
                                id: {{ $taskList->id }},
                                updateUrl: @js(route('projects.tasklists.update', [$project, $taskList])),
                                deleteUrl: @js(route('projects.tasklists.destroy', [$project, $taskList])),
                                name: @js($taskList->name),
                                description: @js($taskList->description),
                                ownerId: @js($taskList->owner_id),
                                milestoneId: @js($taskList->milestone_id)
                            })">
                            <i class="feather-edit-2 me-2"></i>{{ __('projects.edit_tasklist') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction(@js(__('projects.confirm_remove_tasklist')), function () { document.getElementById('taskListDeleteForm{{ $taskList->id }}').submit(); })">
                            <i class="feather-trash-2 me-2"></i>{{ __('projects.remove') }}
                        </a>
                    </li>
                </x-ui.action-dropdown>
                <form id="taskListDeleteForm{{ $taskList->id }}" method="POST" action="{{ route('projects.tasklists.destroy', [$project, $taskList]) }}" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </div>
    </div>

    @php $taskListVisibleLimit = 10; @endphp
    <div id="{{ $taskListBodyId }}" class="collapse show" data-task-list-body>
        <div id="{{ $taskListBodyId }}Rows" class="p-3 pt-2" data-task-list-rows>
            @forelse ($listTasks as $task)
                <div data-task-row data-task-index="{{ $loop->index }}" @class(['d-none' => $loop->index >= $taskListVisibleLimit])>
                    @include('modules.projects.tasklists._task-row')
                </div>
            @empty
                <div class="text-center py-3">
                    <div class="avatar-text avatar-md bg-soft-secondary text-secondary mx-auto mb-2">
                        <i class="feather-check-square"></i>
                    </div>
                    <div class="fs-13 fw-semibold text-dark mb-1">{{ __('projects.no_tasks') }}</div>
                    <p class="fs-11 text-muted mb-0">{{ __('projects.no_tasks_hint') }}</p>
                </div>
            @endforelse
        </div>

        @if ($listTasks->count() > $taskListVisibleLimit)
            @php $hiddenTaskCount = $listTasks->count() - $taskListVisibleLimit; @endphp
            <div class="px-3 pb-3">
                <button type="button" class="btn btn-light btn-sm w-100 task-list-show-more" data-task-list-show-more
                        data-expanded="0"
                        data-label-more="{{ __('projects.show_more_tasks', ['count' => $hiddenTaskCount]) }}"
                        data-label-less="{{ __('projects.show_less_tasks') }}"
                        aria-expanded="false" aria-controls="{{ $taskListBodyId }}Rows">
                    <span data-show-more-label>{{ __('projects.show_more_tasks', ['count' => $hiddenTaskCount]) }}</span>
                    <i class="feather-chevron-down ms-1 task-list-show-more-icon"></i>
                </button>
            </div>
        @endif
    </div>
</div>
