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
