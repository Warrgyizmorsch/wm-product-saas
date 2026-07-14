@php
    $taskFilters = $taskFilters ?? [];
    $hasActiveTaskFilters = $hasActiveTaskFilters ?? false;
    $assigneeOptions = $members->where('is_active', true)->sortBy(fn ($member) => $member->user?->name);
    $visibleTaskLists = $hasActiveTaskFilters
        ? $taskLists->filter(fn ($taskList) => $tasksByList->get($taskList->id, collect())->isNotEmpty())
        : $taskLists;
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h5 class="fw-bold text-dark mb-0">
        <i class="feather-list me-2 text-primary"></i>{{ __('projects.tasklists') }}
    </h5>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.show', $project) }}" class="d-inline">
            <input type="hidden" name="tab" value="tasklists">
            <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i>{{ __('projects.filter_options') }}</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.search_keywords') }}</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('projects.task_search_placeholder') }}" value="{{ $taskFilters['search'] ?? '' }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') }}</label>
                        <x-ui.odoo-form-ui type="select" name="status">
                            <option value="">{{ __('projects.all_statuses') }}</option>
                            @foreach ($taskStatuses as $statusOption)
                                <option value="{{ $statusOption }}" @selected(($taskFilters['status'] ?? '') === $statusOption)>
                                    {{ __('projects.task_statuses.' . $statusOption) }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.priority') }}</label>
                        <x-ui.odoo-form-ui type="select" name="priority">
                            <option value="">{{ __('projects.all_priorities') }}</option>
                            @foreach ($taskPriorities as $priorityOption)
                                <option value="{{ $priorityOption }}" @selected(($taskFilters['priority'] ?? '') === $priorityOption)>
                                    {{ __('projects.task_priorities.' . $priorityOption) }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.task_assignee') }}</label>
                        <x-ui.odoo-form-ui type="select" name="assignee_id" select2Selector="default">
                            <option value="">{{ __('projects.all_assignees') }}</option>
                            @foreach ($assigneeOptions as $member)
                                <option value="{{ $member->user_id }}" @selected((string) ($taskFilters['assignee_id'] ?? '') === (string) $member->user_id)>
                                    {{ $member->user?->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'tasklists']) }}" class="btn btn-sm btn-light border">{{ __('projects.reset') }}</a>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.apply_filters') }}</button>
                    </div>
                </x-ui.filter>
        </form>
        @if ($canManageTaskLists)
            <button type="button" class="btn btn-primary btn-sm" onclick="openTaskListModal('add')">
                <i class="feather-plus me-1"></i>{{ __('projects.add_tasklist') }}
            </button>
        @endif
    </div>
</div>

@if ($hasActiveTaskFilters)
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if (!empty($taskFilters['search']))
            <x-ui.badge variant="secondary" soft>{{ __('projects.search_keywords') }}: "{{ $taskFilters['search'] }}"</x-ui.badge>
        @endif
        @if (!empty($taskFilters['status']))
            <x-ui.badge variant="secondary" soft>{{ __('projects.status') }}: {{ __('projects.task_statuses.' . $taskFilters['status']) }}</x-ui.badge>
        @endif
        @if (!empty($taskFilters['priority']))
            <x-ui.badge variant="secondary" soft>{{ __('projects.priority') }}: {{ __('projects.task_priorities.' . $taskFilters['priority']) }}</x-ui.badge>
        @endif
        @if (!empty($taskFilters['assignee_id']))
            @php $assigneeChip = $assigneeOptions->firstWhere('user_id', (int) $taskFilters['assignee_id']); @endphp
            <x-ui.badge variant="secondary" soft>{{ __('projects.task_assignee') }}: {{ $assigneeChip->user?->name ?? __('projects.unassigned') }}</x-ui.badge>
        @endif
        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'tasklists']) }}" class="fs-11 text-danger fw-semibold">
            <i class="feather-x me-1"></i>{{ __('projects.clear_filters') }}
        </a>
    </div>
@endif

@forelse ($visibleTaskLists as $index => $taskList)
    @include('modules.projects.tasklists._list-card')
@empty
    <div class="text-center py-5">
        <div class="avatar-text avatar-lg bg-soft-primary text-primary mx-auto mb-3">
            <i class="feather-{{ $hasActiveTaskFilters ? 'search' : 'list' }} fs-2"></i>
        </div>
        @if ($hasActiveTaskFilters)
            <h6 class="fw-bold text-dark mb-1">{{ __('projects.no_matching_tasks') }}</h6>
            <p class="fs-12 text-muted mb-3">{{ __('projects.no_matching_tasks_hint') }}</p>
            <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'tasklists']) }}" class="btn btn-light btn-sm border">
                {{ __('projects.clear_filters') }}
            </a>
        @else
            <h6 class="fw-bold text-dark mb-1">{{ __('projects.no_tasklists') }}</h6>
            <p class="fs-12 text-muted mb-3">{{ __('projects.no_tasklists_hint') }}</p>
        @endif
    </div>
@endforelse

@if ($canManageTaskLists)
    @include('modules.projects.tasklists._modal')
    @include('modules.projects.tasklists._drawer')

    @if ($errors->any() && in_array(old('_tasklist_form'), ['add', 'edit'], true))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    @if (old('_tasklist_form') === 'edit')
                        openTaskListModal('edit', {
                            id: {{ (int) old('_tasklist_id') }},
                            updateUrl: @js(route('projects.tasklists.update', [$project, (int) old('_tasklist_id')])),
                            deleteUrl: @js(route('projects.tasklists.destroy', [$project, (int) old('_tasklist_id')])),
                            name: @js(old('name')),
                            description: @js(old('description')),
                            ownerId: @js(old('owner_id')),
                            milestoneId: @js(old('milestone_id')),
                        });
                    @else
                        openTaskListModal('add', {
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
    @include('modules.projects.tasks._modal')
    @include('modules.projects.tasks._drawer')

    @if ($errors->any() && in_array(old('_task_form'), ['add', 'edit'], true))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    @if (old('_task_form') === 'edit')
                        openTaskModal('edit', {
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
                        openTaskModal('add', {
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

@push('styles')
    <style>
        .task-list-toggle-icon,
        .task-list-show-more-icon {
            transition: transform 0.2s ease;
        }
        .task-list-toggle.collapsed .task-list-toggle-icon {
            transform: rotate(-90deg);
        }
        .task-list-show-more.is-expanded .task-list-show-more-icon {
            transform: rotate(180deg);
        }
        .task-list-scroll {
            max-height: 420px;
            overflow-y: auto;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var STORAGE_PREFIX = 'projectTaskListCollapsed_';
            var VISIBLE_LIMIT = 10;

            function isCollapsed(taskListId) {
                try {
                    return localStorage.getItem(STORAGE_PREFIX + taskListId) === '1';
                } catch (e) {
                    return false;
                }
            }

            function rememberCollapsed(taskListId, collapsed) {
                try {
                    localStorage.setItem(STORAGE_PREFIX + taskListId, collapsed ? '1' : '0');
                } catch (e) {
                    // localStorage unavailable (e.g. private browsing) - ignore, defaults to expanded
                }
            }

            function applySavedCollapseState() {
                document.querySelectorAll('[data-task-list-card]').forEach(function (card) {
                    var taskListId = card.getAttribute('data-task-list-id');
                    var body = card.querySelector('[data-task-list-body]');
                    var toggle = card.querySelector('[data-task-list-toggle]');

                    if (!body || !toggle || !isCollapsed(taskListId)) return;

                    body.classList.remove('show');
                    toggle.classList.add('collapsed');
                    toggle.setAttribute('aria-expanded', 'false');
                });
            }

            function bindCollapsePersistence() {
                document.addEventListener('shown.bs.collapse', function (e) {
                    var card = e.target.closest('[data-task-list-card]');
                    if (card) rememberCollapsed(card.getAttribute('data-task-list-id'), false);
                });
                document.addEventListener('hidden.bs.collapse', function (e) {
                    var card = e.target.closest('[data-task-list-card]');
                    if (card) rememberCollapsed(card.getAttribute('data-task-list-id'), true);
                });
            }

            function bindShowMoreToggle() {
                document.addEventListener('click', function (e) {
                    var button = e.target.closest('[data-task-list-show-more]');
                    if (!button) return;

                    var card = button.closest('[data-task-list-card]');
                    var rowsContainer = card ? card.querySelector('[data-task-list-rows]') : null;
                    if (!rowsContainer) return;

                    var expanded = button.getAttribute('data-expanded') !== '1';
                    var label = button.querySelector('[data-show-more-label]');

                    rowsContainer.querySelectorAll('[data-task-row]').forEach(function (row) {
                        if (parseInt(row.getAttribute('data-task-index'), 10) >= VISIBLE_LIMIT) {
                            row.classList.toggle('d-none', !expanded);
                        }
                    });

                    button.setAttribute('data-expanded', expanded ? '1' : '0');
                    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    button.classList.toggle('is-expanded', expanded);
                    rowsContainer.classList.toggle('task-list-scroll', expanded);

                    if (label) {
                        label.textContent = expanded
                            ? button.getAttribute('data-label-less')
                            : button.getAttribute('data-label-more');
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                applySavedCollapseState();
                bindCollapsePersistence();
                bindShowMoreToggle();
            });
        })();
    </script>
@endpush
