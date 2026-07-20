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

    // Mirrors MilestoneService::buildKpiSummary()'s task-weighted progress so the
    // hero's percentage never drifts from the "X of Y tasks completed" caption
    // next to it. $milestone->completion_percentage is only a manually-set value
    // (see the edit modal) and isn't kept in sync with actual task completion.
    $heroTaskProgress = $totalTasks > 0
        ? (int) round(($completedTasks / $totalTasks) * 100)
        : (int) ($milestone->completion_percentage ?? 0);

    // At 100% the bar should always read as done, even if health_state resolved to
    // "not_applicable" (secondary/gray) because the milestone's status is Completed.
    $heroProgressVariant = $heroTaskProgress >= 100 ? 'success' : $heroHealthVariant;

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

    $heroStatusOptions = collect(\App\Domains\Projects\Models\Milestone::STATUSES)
        ->mapWithKeys(fn ($status) => [$status => __('projects.statuses.' . $status)]);

    $heroOwnerOptions = $canManageMilestones
        ? $tenantUsers->pluck('name', 'id')->prepend(__('projects.none_option'), '')
        : collect();
@endphp

<div class="border rounded-3 p-3 p-md-4 mb-4 bg-white">
    {{-- Band A: identity + actions --}}
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div class="d-flex align-items-start gap-2">
            <span class="milestone-icon-badge rounded-3 bg-light border flex-shrink-0" aria-hidden="true">
                <i class="feather-flag text-muted"></i>
            </span>
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    @if ($canManageMilestones)
                        <x-ui.inline-edit field="name" :value="$milestone->name"
                            :url="route('projects.milestones.field', [$project, $milestone])" :label="__('projects.milestone_name')" />
                    @else
                        {{ $milestone->name }}
                    @endif
                </h4>
                <div class="d-flex flex-wrap align-items-center gap-2 fs-13 mt-1">
                    <x-ui.badge variant="{{ $heroStatusVariant }}" soft>
                        {{ __('projects.statuses.' . $milestone->status) }}
                    </x-ui.badge>
                    <span class="d-inline-flex align-items-center gap-1 text-muted">
                        <span class="health-dot bg-{{ $heroHealthVariant }}"></span>
                        {{ __('projects.health_states.' . $milestone->health_state) }}
                        @if ($milestone->health_reason)
                            <i class="feather-info fs-14" tabindex="0" role="button" aria-label="{{ __('projects.health') }}: {{ $milestone->health_reason }}" data-bs-toggle="tooltip" title="{{ $milestone->health_reason }}" style="cursor: pointer;"></i>
                        @endif
                    </span>
                </div>
                @if ($canManageMilestones)
                    <div class="fs-13 text-muted mb-0 mt-1">
                        <x-ui.inline-edit field="description" :value="$milestone->description"
                            :url="route('projects.milestones.field', [$project, $milestone])" type="textarea" :label="__('projects.description')" />
                    </div>
                @elseif ($milestone->description)
                    <p class="fs-13 text-muted mb-0 mt-1 text-clamp-2">{{ $milestone->description }}</p>
                @endif
            </div>
        </div>

        @if ($canManageMilestones)
            <div class="hstack gap-2 flex-shrink-0">
                <a href="javascript:void(0);" class="action-dropdown-btn action-dropdown-btn--accent" title="{{ __('projects.edit_milestone') }}" aria-label="{{ __('projects.edit_milestone') }}" data-bs-toggle="tooltip"
                   onclick="openMilestoneModal('edit', @js($heroJsData))">
                    <i class="feather feather-edit-2"></i>
                </a>
                <x-ui.action-dropdown id="milestoneWorkspaceActions">
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

    {{-- Band D: metadata strip + progress --}}
    <div class="d-flex flex-wrap align-items-start gap-3 mt-4">
        <div class="d-flex align-items-start gap-2 metadata-item">
            <i class="feather-list text-muted fs-14 metadata-icon"></i>
            <div class="d-flex flex-column">
                <span class="fs-13 fw-semibold text-dark">{{ $totalTasks }}</span>
                <span class="fs-11 text-muted">{{ __('projects.tasks') }}</span>
            </div>
        </div>
        <div class="d-flex align-items-start gap-2 ps-3 metadata-divider metadata-item">
            <i class="feather-flag text-muted fs-14 metadata-icon"></i>
            <div class="d-flex flex-column">
                <span class="fs-13 fw-semibold text-dark">
                    @if ($canManageMilestones)
                        <x-ui.inline-edit field="status" :value="$milestone->status"
                            :url="route('projects.milestones.field', [$project, $milestone])" type="select" :options="$heroStatusOptions" :label="__('projects.status')" />
                    @else
                        {{ __('projects.statuses.' . $milestone->status) }}
                    @endif
                </span>
                <span class="fs-11 text-muted">{{ __('projects.status') }}</span>
            </div>
        </div>
        <div class="d-flex align-items-start gap-2 ps-3 metadata-divider metadata-item">
            <i class="feather-user text-muted fs-14 metadata-icon"></i>
            <div class="d-flex flex-column">
                <span class="fs-13 fw-semibold text-dark">
                    @if ($canManageMilestones)
                        <x-ui.inline-edit field="owner_id" :value="$milestone->owner_id"
                            :url="route('projects.milestones.field', [$project, $milestone])" type="select2" :options="$heroOwnerOptions" :label="__('projects.milestone_owner')" />
                    @else
                        {{ $milestone->owner?->name ?: '—' }}
                    @endif
                </span>
                <span class="fs-11 text-muted">{{ __('projects.milestone_owner') }}</span>
            </div>
        </div>
        <div class="d-flex align-items-start gap-2 ps-3 metadata-divider metadata-item">
            <i class="feather-calendar text-muted fs-14 metadata-icon"></i>
            <div class="d-flex flex-column">
                <span class="fs-13 fw-semibold text-dark">
                    @if ($canManageMilestones)
                        <x-ui.inline-edit field="start_date" :value="$milestone->start_date"
                            :url="route('projects.milestones.field', [$project, $milestone])" type="date" :label="__('projects.start_date')" />
                    @else
                        {{ $milestone->start_date?->format('d/m/Y') ?: '—' }}
                    @endif
                </span>
                <span class="fs-11 text-muted">{{ __('projects.start_date') }}</span>
            </div>
        </div>
        <div class="d-flex align-items-start gap-2 ps-3 metadata-divider metadata-item">
            <i class="feather-calendar text-muted fs-14 metadata-icon"></i>
            <div class="d-flex flex-column">
                <span class="fs-13 fw-semibold text-dark">
                    @if ($canManageMilestones)
                        <x-ui.inline-edit field="due_date" :value="$milestone->due_date"
                            :url="route('projects.milestones.field', [$project, $milestone])" type="date" :label="__('projects.due_date')" />
                    @else
                        {{ $milestone->due_date?->format('d/m/Y') ?: '—' }}
                    @endif
                </span>
                <span class="fs-11 text-muted">{{ __('projects.due_date') }}</span>
            </div>
        </div>

        <div class="ms-auto">
            <div class="d-flex align-items-center gap-2">
                <div class="progress" style="width: 130px; height: 4px;">
                    <div class="progress-bar bg-{{ $heroProgressVariant }}" style="width: {{ $heroTaskProgress }}%"></div>
                </div>
                <span class="fs-13 fw-semibold text-dark">{{ $heroTaskProgress }}%</span>
            </div>
            <div class="fs-12 text-muted mt-1">
                {{ __('projects.milestone_progress_caption', ['completed' => $completedTasks, 'total' => $totalTasks]) }}
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .milestone-icon-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .health-dot {
                display: inline-block;
                width: 9px;
                height: 9px;
                border-radius: 50%;
            }

            .text-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .action-dropdown-btn--accent {
                border-color: var(--bs-primary) !important;
                color: var(--bs-primary) !important;
            }

            .metadata-divider {
                border-left: 1px solid var(--bs-border-color);
            }

            .metadata-icon {
                margin-top: 2px;
                line-height: 1;
            }

            .metadata-item .fs-13 {
                line-height: 1.3;
            }

            .metadata-item .fs-11 {
                line-height: 1.3;
            }

            @media (max-width: 767.98px) {
                .metadata-divider {
                    border-left: none;
                }
            }
        </style>
    @endpush
@endonce
