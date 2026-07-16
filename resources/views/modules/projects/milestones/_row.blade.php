@php
    $rowStatusVariant = match ($milestone->status) {
        'Active' => 'success',
        'On Hold' => 'warning',
        'Completed' => 'primary',
        'Closed' => 'dark',
        default => 'secondary',
    };

    $rowHealthVariant = match ($milestone->health_state) {
        'on_track' => 'success',
        'at_risk' => 'warning',
        'off_track' => 'danger',
        'blocked' => 'dark',
        default => 'secondary',
    };

    $rowTasksTotal = (int) ($milestone->tasks_count ?? 0);
    $rowTasksDone = (int) ($milestone->completed_tasks_count ?? 0);

    $rowJsData = [
        'id' => $milestone->id,
        'updateUrl' => route('projects.milestones.update', [$project, $milestone->id]),
        'deleteUrl' => route('projects.milestones.destroy', [$project, $milestone->id]),
        'name' => $milestone->name,
        'description' => $milestone->description,
        'ownerId' => $milestone->owner_id,
        'ownerName' => $milestone->owner?->name,
        'startDate' => $milestone->start_date?->format('Y-m-d'),
        'dueDate' => $milestone->due_date?->format('Y-m-d'),
        'startDateDisplay' => $milestone->start_date?->format('d/m/Y'),
        'dueDateDisplay' => $milestone->due_date?->format('d/m/Y'),
        'status' => $milestone->status,
        'completionPercentage' => $milestone->completion_percentage,
    ];

    $rowCloneJsData = array_merge($rowJsData, [
        'name' => $milestone->name ? $milestone->name . ' ' . __('projects.clone_suffix') : '',
    ]);
@endphp

<div class="milestone-row border-bottom py-3 px-2 px-md-3">
    {{-- Desktop / tablet dense row --}}
    <div class="d-none d-md-flex align-items-center gap-3">
        <div class="avatar-text avatar-md rounded-circle bg-soft-{{ $rowHealthVariant }} text-{{ $rowHealthVariant }} flex-shrink-0">
            <i class="feather-flag"></i>
        </div>

        <div class="flex-grow-1" style="min-width: 180px; max-width: 260px;">
            <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}" class="fw-semibold text-dark text-truncate d-block text-decoration-none">
                {{ $milestone->name }}
            </a>
            @if ($milestone->owner)
                <div class="fs-11 text-muted text-truncate"><i class="feather-user me-1"></i>{{ $milestone->owner->name }}</div>
            @endif
        </div>

        <div style="min-width: 140px; width: 160px;" class="flex-shrink-0">
            <div class="d-flex justify-content-between fs-11 text-muted mb-1">
                <span>{{ $rowTasksDone }} / {{ $rowTasksTotal }}</span>
                <span class="fw-semibold">{{ $milestone->completion_percentage }}%</span>
            </div>
            <div class="progress ht-6">
                <div class="progress-bar bg-{{ $rowHealthVariant }}" style="width: {{ $milestone->completion_percentage }}%"></div>
            </div>
        </div>

        <div class="fs-12 text-muted flex-shrink-0" style="min-width: 160px;">
            <div>{{ $milestone->start_date?->format('d/m/Y') ?: '—' }} &rarr; {{ $milestone->due_date?->format('d/m/Y') ?: '—' }}</div>
        </div>

        <div class="flex-shrink-0" style="min-width: 110px;" title="{{ $milestone->health_reason }}">
            <x-ui.badge variant="{{ $rowHealthVariant }}" soft>
                {{ __('projects.health_states.' . $milestone->health_state) }}
            </x-ui.badge>
        </div>

        <div class="flex-shrink-0" style="min-width: 100px;">
            @if ($milestone->status)
                <x-ui.badge variant="{{ $rowStatusVariant }}" soft>
                    {{ __('projects.statuses.' . $milestone->status) }}
                </x-ui.badge>
            @else
                <span class="text-muted">—</span>
            @endif
        </div>

        @if ($canManageMilestones)
            <div class="flex-shrink-0 ms-auto">
                <x-ui.action-dropdown id="milestoneActions{{ $milestone->id }}">
                    <li>
                        <a class="dropdown-item" href="{{ route('projects.milestones.show', [$project, $milestone]) }}">
                            <i class="feather-eye me-2"></i>{{ __('projects.view_milestone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="openMilestoneModal('edit', @js($rowJsData))">
                            <i class="feather-edit-2 me-2"></i>{{ __('projects.edit_milestone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="openMilestoneModal('add', @js($rowCloneJsData))">
                            <i class="feather-copy me-2"></i>{{ __('projects.clone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item text-danger" href="javascript:void(0);"
                           onclick="confirmAction(@js(__('projects.confirm_remove_milestone')), function () { document.getElementById('milestoneRowDeleteForm{{ $milestone->id }}').submit(); })">
                            <i class="feather-trash-2 me-2"></i>{{ __('projects.remove') }}
                        </a>
                    </li>
                </x-ui.action-dropdown>
                <form id="milestoneRowDeleteForm{{ $milestone->id }}" method="POST" action="{{ route('projects.milestones.destroy', [$project, $milestone->id]) }}" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        @endif
    </div>

    {{-- Mobile stacked layout --}}
    <div class="d-md-none">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="d-flex align-items-start gap-2 flex-grow-1" style="min-width: 0;">
                <div class="avatar-text avatar-sm rounded-circle bg-soft-{{ $rowHealthVariant }} text-{{ $rowHealthVariant }} flex-shrink-0">
                    <i class="feather-flag"></i>
                </div>
                <div class="flex-grow-1" style="min-width: 0;">
                    <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}" class="fw-semibold text-dark text-truncate d-block text-decoration-none">
                        {{ $milestone->name }}
                    </a>
                    @if ($milestone->owner)
                        <div class="fs-11 text-muted text-truncate"><i class="feather-user me-1"></i>{{ $milestone->owner->name }}</div>
                    @endif
                </div>
            </div>
            @if ($canManageMilestones)
                <x-ui.action-dropdown id="milestoneActionsMobile{{ $milestone->id }}">
                    <li>
                        <a class="dropdown-item" href="{{ route('projects.milestones.show', [$project, $milestone]) }}">
                            <i class="feather-eye me-2"></i>{{ __('projects.view_milestone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="openMilestoneModal('edit', @js($rowJsData))">
                            <i class="feather-edit-2 me-2"></i>{{ __('projects.edit_milestone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="openMilestoneModal('add', @js($rowCloneJsData))">
                            <i class="feather-copy me-2"></i>{{ __('projects.clone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item text-danger" href="javascript:void(0);"
                           onclick="confirmAction(@js(__('projects.confirm_remove_milestone')), function () { document.getElementById('milestoneRowDeleteFormMobile{{ $milestone->id }}').submit(); })">
                            <i class="feather-trash-2 me-2"></i>{{ __('projects.remove') }}
                        </a>
                    </li>
                </x-ui.action-dropdown>
                <form id="milestoneRowDeleteFormMobile{{ $milestone->id }}" method="POST" action="{{ route('projects.milestones.destroy', [$project, $milestone->id]) }}" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </div>

        <div class="mt-2">
            <div class="d-flex justify-content-between fs-11 text-muted mb-1">
                <span>{{ $rowTasksDone }} / {{ $rowTasksTotal }} {{ __('projects.tasks') }}</span>
                <span class="fw-semibold">{{ $milestone->completion_percentage }}%</span>
            </div>
            <div class="progress ht-6">
                <div class="progress-bar bg-{{ $rowHealthVariant }}" style="width: {{ $milestone->completion_percentage }}%"></div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <x-ui.badge variant="{{ $rowHealthVariant }}" soft>
                {{ __('projects.health_states.' . $milestone->health_state) }}
            </x-ui.badge>
            @if ($milestone->status)
                <x-ui.badge variant="{{ $rowStatusVariant }}" soft>
                    {{ __('projects.statuses.' . $milestone->status) }}
                </x-ui.badge>
            @endif
            <span class="fs-11 text-muted">{{ $milestone->start_date?->format('d/m/Y') ?: '—' }} &rarr; {{ $milestone->due_date?->format('d/m/Y') ?: '—' }}</span>
        </div>
    </div>
</div>
