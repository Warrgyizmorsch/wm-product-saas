@props([
    'project' => null,
    'name' => null,
    'client' => null,
    'description' => null,
    'status' => 'in_progress',
    'priority' => 'medium',
    'health' => null, // on_track, at_risk, off_track
    'progress' => 0,
    'dueDate' => null,
    'milestoneCount' => 0,
    'taskCount' => 0,
    'completedTaskCount' => 0,
    'users' => [],
    'icon' => 'feather-briefcase',
    'color' => 'primary',
])

@php
    // Extract values if project object/array is passed
    if ($project) {
        $p = is_array($project) ? $project : $project->toArray();
        $name = $name ?? ($p['name'] ?? $p['title'] ?? 'Untitled Project');
        $client = $client ?? ($p['client'] ?? $p['client_name'] ?? $p['customer'] ?? null);
        $description = $description ?? ($p['description'] ?? null);
        $status = $status !== 'in_progress' ? $status : ($p['status'] ?? 'in_progress');
        $priority = $priority !== 'medium' ? $priority : ($p['priority'] ?? 'medium');
        $health = $health ?? ($p['health'] ?? null);
        $progress = $progress ?: ($p['progress'] ?? $p['completion_percentage'] ?? 0);
        $dueDate = $dueDate ?? ($p['due_date'] ?? $p['end_date'] ?? null);
        $milestoneCount = $milestoneCount ?: ($p['milestones_count'] ?? $p['milestone_count'] ?? 0);
        $taskCount = $taskCount ?: ($p['tasks_count'] ?? $p['task_count'] ?? 0);
        $completedTaskCount = $completedTaskCount ?: ($p['completed_tasks_count'] ?? 0);
        $users = !empty($users) ? $users : ($p['users'] ?? $p['members'] ?? $p['assignees'] ?? []);
        $icon = $icon !== 'feather-briefcase' ? $icon : ($p['icon'] ?? 'feather-briefcase');
        $color = $color !== 'primary' ? $color : ($p['color'] ?? 'primary');
    }

    $healthMap = [
        'on_track' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'label' => 'On Track'],
        'at_risk' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'label' => 'At Risk'],
        'off_track' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'label' => 'Off Track'],
    ];
    $healthConfig = $health ? ($healthMap[strtolower($health)] ?? null) : null;
@endphp

<div {{ $attributes->merge(['class' => 'card stretch stretch-full border-0 shadow-sm mb-4 h-100']) }}>
    <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
            <!-- Header Row -->
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="d-flex align-items-center gap-3">
                    <x-ui.icon-tile :icon="$icon" :color="$color" size="lg" />
                    <div>
                        <h5 class="fw-bold text-dark mb-1 text-truncate-1-line fs-15">{{ $name }}</h5>
                        @if ($client)
                            <span class="fs-12 text-muted fw-medium d-block">
                                <i class="feather-user me-1"></i>{{ $client }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <x-ui.status-badge :status="$status" size="sm" />

                    @if (isset($actions))
                        <div class="dropdown">
                            <a href="javascript:void(0)" class="avatar-text avatar-sm text-muted rounded-circle" data-bs-toggle="dropdown">
                                <i class="feather-more-vertical fs-14"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                {{ $actions }}
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Description -->
            @if ($description)
                <p class="fs-13 text-muted mb-3 text-truncate-2-lines line-clamp-2" style="min-height: 38px;">
                    {{ $description }}
                </p>
            @endif

            <!-- Badges Row (Priority & Health) -->
            <div class="d-flex align-items-center gap-2 mb-3">
                <x-ui.priority-badge :priority="$priority" />
                @if ($healthConfig)
                    <span class="badge {{ $healthConfig['bg'] }} {{ $healthConfig['text'] }} px-2 py-1 fs-11 fw-semibold">
                        <i class="feather-heart fs-10 me-1"></i>{{ __($healthConfig['label']) }}
                    </span>
                @endif
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <x-ui.progress-bar :value="$progress" :showLabel="true" />
            </div>
        </div>

        <div>
            <!-- Footer Meta & Assignees -->
            <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3 fs-12 text-muted">
                    @if ($taskCount > 0)
                        <span title="{{ __('Tasks') }}" data-bs-toggle="tooltip">
                            <i class="feather-check-square me-1 text-primary"></i>{{ $completedTaskCount }}/{{ $taskCount }}
                        </span>
                    @endif

                    @if ($milestoneCount > 0)
                        <span title="{{ __('Milestones') }}" data-bs-toggle="tooltip">
                            <i class="feather-flag me-1 text-warning"></i>{{ $milestoneCount }}
                        </span>
                    @endif

                    @if ($dueDate)
                        <span title="{{ __('Due Date') }}" data-bs-toggle="tooltip">
                            <i class="feather-calendar me-1 text-danger"></i>{{ $dueDate }}
                        </span>
                    @endif
                </div>

                <div>
                    <x-ui.avatar-group :users="$users" size="sm" :max="3" />
                </div>
            </div>
        </div>
    </div>
</div>
