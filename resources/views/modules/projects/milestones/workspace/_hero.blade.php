@php
    $heroStatusVariant = match ($milestone->status) {
        'Active' => 'success',
        'On Hold' => 'warning',
        'Completed' => 'primary',
        'Closed' => 'dark',
        default => 'secondary',
    };

    $heroHealthVariant = match ($milestone->health_state) {
        'on_track' => 'success',
        'at_risk' => 'warning',
        'off_track' => 'danger',
        'blocked' => 'dark',
        default => 'secondary',
    };

    $heroJsData = [
        'id' => $milestone->id,
        'updateUrl' => route('projects.milestones.update', [$project, $milestone]),
        'deleteUrl' => route('projects.milestones.destroy', [$project, $milestone]),
        'name' => $milestone->name,
        'description' => $milestone->description,
        'ownerId' => $milestone->owner_id,
        'ownerName' => $milestone->owner?->name,
        'startDate' => $milestone->start_date?->format('Y-m-d'),
        'dueDate' => $milestone->due_date?->format('Y-m-d'),
        'status' => $milestone->status,
        'completionPercentage' => $milestone->completion_percentage,
    ];

    $heroCloneJsData = array_merge($heroJsData, [
        'name' => $milestone->name ? $milestone->name . ' ' . __('projects.clone_suffix') : '',
    ]);
@endphp

<div class="border rounded-3 p-3 p-md-4 mb-4 bg-light">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'milestones']) }}" class="fs-12 text-muted text-decoration-none mb-2 d-inline-flex align-items-center">
                <i class="feather-arrow-left me-1"></i>{{ __('projects.back_to_project') }}
            </a>
            <h4 class="fw-bold text-dark mb-0 d-flex flex-wrap align-items-center gap-2">
                <i class="feather-flag text-{{ $heroHealthVariant }}"></i>
                {{ $milestone->name }}
                <x-ui.badge variant="{{ $heroStatusVariant }}" soft>
                    {{ __('projects.statuses.' . $milestone->status) }}
                </x-ui.badge>
                <span title="{{ $milestone->health_reason }}">
                    <x-ui.badge variant="{{ $heroHealthVariant }}" soft>
                        {{ __('projects.health_states.' . $milestone->health_state) }}
                    </x-ui.badge>
                </span>
            </h4>
            @if ($milestone->description)
                <p class="fs-13 text-muted mb-0 mt-2">{{ $milestone->description }}</p>
            @endif
        </div>

        @if ($canManageMilestones)
            <div class="flex-shrink-0">
                <x-ui.action-dropdown id="milestoneWorkspaceActions">
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="openMilestoneModal('edit', @js($heroJsData))">
                            <i class="feather-edit-2 me-2"></i>{{ __('projects.edit_milestone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="openMilestoneModal('add', @js($heroCloneJsData))">
                            <i class="feather-copy me-2"></i>{{ __('projects.clone') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item text-danger" href="javascript:void(0);"
                           onclick="confirmAction(@js(__('projects.confirm_remove_milestone')), function () { document.getElementById('milestoneWorkspaceDeleteForm').submit(); })">
                            <i class="feather-trash-2 me-2"></i>{{ __('projects.remove') }}
                        </a>
                    </li>
                </x-ui.action-dropdown>
                <form id="milestoneWorkspaceDeleteForm" method="POST" action="{{ route('projects.milestones.destroy', [$project, $milestone]) }}" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        @endif
    </div>

    <div class="row g-3 align-items-center">
        <div class="col-md-5">
            <div class="d-flex justify-content-between fs-12 text-muted mb-1">
                <span>{{ __('projects.progress') }}</span>
                <span class="fw-semibold">{{ $completedTasks }} / {{ $totalTasks }} {{ __('projects.tasks') }} &middot; {{ $milestone->completion_percentage }}%</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-{{ $heroHealthVariant }}" style="width: {{ $milestone->completion_percentage }}%"></div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.milestone_owner') }}</div>
            <div class="fs-13 fw-semibold text-dark"><i class="feather-user me-1 text-muted"></i>{{ $milestone->owner?->name ?: '—' }}</div>
        </div>
        <div class="col-md-2 col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.start_date') }}</div>
            <div class="fs-13 fw-semibold text-dark">{{ $milestone->start_date?->format('d/m/Y') ?: '—' }}</div>
        </div>
        <div class="col-md-2 col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.due_date') }}</div>
            <div class="fs-13 fw-semibold text-dark">{{ $milestone->due_date?->format('d/m/Y') ?: '—' }}</div>
        </div>
    </div>
</div>
