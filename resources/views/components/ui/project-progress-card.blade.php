@props([
    'project' => null,
    'title' => null,
    'progress' => 0, // precomputed percentage
    'daysRemaining' => null, // precomputed days count e.g. 14
    'tasksCompleted' => 0,
    'tasksTotal' => 0,
    'milestonesCompleted' => 0,
    'milestonesTotal' => 0,
    'health' => 'on_track', // on_track, at_risk, off_track
    'dueDate' => null,
    'lastUpdated' => null,
    'users' => [],
])

@php
    if ($project) {
        $p = is_array($project) ? $project : $project->toArray();
        $title = $title ?? ($p['title'] ?? $p['name'] ?? 'Untitled Project');
        $progress = $progress ?: ($p['progress'] ?? 0);
        $daysRemaining = $daysRemaining ?? ($p['days_remaining'] ?? null);
        $tasksCompleted = $tasksCompleted ?: ($p['tasks_completed'] ?? 0);
        $tasksTotal = $tasksTotal ?: ($p['tasks_total'] ?? 0);
        $milestonesCompleted = $milestonesCompleted ?: ($p['milestones_completed'] ?? 0);
        $milestonesTotal = $milestonesTotal ?: ($p['milestones_total'] ?? 0);
        $health = $health !== 'on_track' ? $health : ($p['health'] ?? 'on_track');
        $dueDate = $dueDate ?? ($p['due_date'] ?? null);
        $lastUpdated = $lastUpdated ?? ($p['last_updated'] ?? null);
        $users = !empty($users) ? $users : ($p['users'] ?? []);
    }

    $healthMap = [
        'on_track' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'label' => 'On Track', 'icon' => 'feather-check-circle'],
        'at_risk' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'label' => 'At Risk', 'icon' => 'feather-alert-triangle'],
        'off_track' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'label' => 'Off Track', 'icon' => 'feather-alert-octagon'],
    ];
    $healthConfig = $healthMap[strtolower($health)] ?? $healthMap['on_track'];
@endphp

<div {{ $attributes->merge(['class' => 'card stretch stretch-full border-0 shadow-sm mb-3']) }}>
    <div class="card-body p-4">
        <!-- Header -->
        <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
                <span class="badge {{ $healthConfig['bg'] }} {{ $healthConfig['text'] }} px-2 py-1 fs-11 fw-bold mb-1 d-inline-flex align-items-center gap-1">
                    <i class="{{ $healthConfig['icon'] }} fs-10"></i>
                    <span>{{ __($healthConfig['label']) }}</span>
                </span>
                <h6 class="fw-bold text-dark mb-0 fs-15 text-truncate-1-line">{{ $title }}</h6>
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

        <!-- Key Ratios Grid -->
        <div class="row g-2 mb-3 text-center">
            <div class="col-4">
                <div class="p-2 bg-light rounded-2">
                    <span class="fs-10 text-uppercase text-muted fw-bold d-block">{{ __('Tasks') }}</span>
                    <span class="fs-12 fw-bold text-dark">{{ $tasksCompleted }}/{{ $tasksTotal }}</span>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-light rounded-2">
                    <span class="fs-10 text-uppercase text-muted fw-bold d-block">{{ __('Milestones') }}</span>
                    <span class="fs-12 fw-bold text-dark">{{ $milestonesCompleted }}/{{ $milestonesTotal }}</span>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-light rounded-2">
                    <span class="fs-10 text-uppercase text-muted fw-bold d-block">{{ __('Remaining') }}</span>
                    <span class="fs-12 fw-bold text-primary">{{ $daysRemaining ?? 'N/A' }} {{ is_numeric($daysRemaining) ? __('Days') : '' }}</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="pt-3 border-top d-flex align-items-center justify-content-between">
            <div class="fs-11 text-muted">
                @if ($dueDate)
                    <span class="d-block"><i class="feather-calendar me-1"></i>Due: {{ $dueDate }}</span>
                @endif
                @if ($lastUpdated)
                    <span class="d-block text-muted opacity-75">Updated {{ $lastUpdated }}</span>
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
