@props([
    'milestone' => null,
    'title' => null,
    'description' => null,
    'status' => 'in_progress',
    'health' => null, // on_track, at_risk, off_track
    'dueDate' => null,
    'progress' => 0, // precomputed percentage 0-100
    'tasksCompleted' => 0,
    'tasksTotal' => 0,
    'users' => [],
])

@php
    if ($milestone) {
        $m = is_array($milestone) ? $milestone : $milestone->toArray();
        $title = $title ?? ($m['title'] ?? $m['name'] ?? 'Untitled Milestone');
        $description = $description ?? ($m['description'] ?? null);
        $status = $status !== 'in_progress' ? $status : ($m['status'] ?? 'in_progress');
        $health = $health ?? ($m['health'] ?? null);
        $dueDate = $dueDate ?? ($m['due_date'] ?? $m['target_date'] ?? null);
        $progress = $progress ?: ($m['progress'] ?? 0);
        $tasksCompleted = $tasksCompleted ?: ($m['tasks_completed'] ?? $m['completed_tasks_count'] ?? 0);
        $tasksTotal = $tasksTotal ?: ($m['tasks_total'] ?? $m['tasks_count'] ?? 0);
        $users = !empty($users) ? $users : ($m['users'] ?? $m['assignees'] ?? []);
    }

    $healthMap = [
        'on_track' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'label' => 'On Track'],
        'at_risk' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'label' => 'At Risk'],
        'off_track' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'label' => 'Off Track'],
    ];
    $healthConfig = $health ? ($healthMap[strtolower($health)] ?? null) : null;
@endphp

<div {{ $attributes->merge(['class' => 'card stretch stretch-full border-0 shadow-sm mb-3']) }}>
    <div class="card-body p-4">
        <!-- Header Row -->
        <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <x-ui.status-badge :status="$status" size="sm" />
                    @if ($healthConfig)
                        <span class="badge {{ $healthConfig['bg'] }} {{ $healthConfig['text'] }} px-2 py-1 fs-11 fw-semibold">
                            <i class="feather-heart fs-10 me-1"></i>{{ __($healthConfig['label']) }}
                        </span>
                    @endif
                </div>
                <h6 class="fw-bold text-dark mb-1 fs-15">{{ $title }}</h6>
                @if ($description)
                    <p class="fs-13 text-muted mb-0 text-truncate-2-lines">{{ $description }}</p>
                @endif
            </div>

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

        <!-- Progress Bar -->
        <div class="mb-3">
            <x-ui.progress-bar :value="$progress" :showLabel="true" />
        </div>

        <!-- Details Slot (Optional Expandable Content) -->
        @if (isset($details))
            <div class="mb-3 p-3 bg-light rounded-3 fs-13 text-muted">
                {{ $details }}
            </div>
        @endif

        <!-- Footer Meta -->
        <div class="pt-3 border-top d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3 fs-12 text-muted">
                @if ($tasksTotal > 0)
                    <span title="{{ __('Tasks Completed') }}" data-bs-toggle="tooltip">
                        <i class="feather-check-square me-1 text-primary"></i>{{ $tasksCompleted }}/{{ $tasksTotal }} {{ __('Tasks') }}
                    </span>
                @endif

                @if ($dueDate)
                    <span title="{{ __('Target Date') }}" data-bs-toggle="tooltip">
                        <i class="feather-calendar me-1 text-warning"></i>{{ $dueDate }}
                    </span>
                @endif
            </div>

            @if (!empty($users))
                <div>
                    <x-ui.avatar-group :users="$users" size="xs" :max="3" />
                </div>
            @endif
        </div>
    </div>
</div>
