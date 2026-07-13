@php
    $previewEventMap = [
        'project.created' => ['icon' => 'feather-folder', 'class' => 'border-primary text-primary bg-soft-primary', 'title' => 'Project Created'],
        'project.updated' => ['icon' => 'feather-edit', 'class' => 'border-info text-info bg-soft-info', 'title' => 'Project Details Updated'],
        'project.status_changed' => ['icon' => 'feather-refresh-cw', 'class' => 'border-warning text-warning bg-soft-warning', 'title' => 'Project Status Changed'],
        'project.deleted' => ['icon' => 'feather-trash-2', 'class' => 'border-danger text-danger bg-soft-danger', 'title' => 'Project Deleted'],
        'milestone.created' => ['icon' => 'feather-flag', 'class' => 'border-secondary text-secondary bg-soft-secondary', 'title' => 'Milestone Created'],
        'milestone.updated' => ['icon' => 'feather-edit', 'class' => 'border-info text-info bg-soft-info', 'title' => 'Milestone Updated'],
        'milestone.completed' => ['icon' => 'feather-check-circle', 'class' => 'border-success text-success bg-soft-success', 'title' => 'Milestone Completed'],
        'milestone.deleted' => ['icon' => 'feather-trash-2', 'class' => 'border-danger text-danger bg-soft-danger', 'title' => 'Milestone Deleted'],
    ];
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="fw-bold text-uppercase text-muted fs-11 mb-0">
        <i class="feather-activity me-1 text-primary"></i>{{ __('projects.project_activity') }}
    </h6>
    <a href="javascript:void(0);" onclick="openActivityDrawer('{{ route('projects.activity', $project) }}')" class="fs-12 fw-semibold text-primary text-decoration-none">
        {{ __('projects.view_all') }}
    </a>
</div>

@forelse ($recentActivities as $activity)
    @php
        $event = $previewEventMap[$activity->event_type] ?? [
            'icon' => 'feather-activity',
            'class' => 'border-secondary text-secondary bg-soft-secondary',
            'title' => ucwords(str_replace(['.', '_'], ' ', $activity->event_type)),
        ];
    @endphp
    <div class="d-flex align-items-start gap-2 {{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
        <div class="avatar-text avatar-sm {{ $event['class'] }}">
            <i class="{{ $event['icon'] }} fs-11"></i>
        </div>
        <div class="flex-grow-1">
            <div class="fw-semibold text-dark fs-12">{{ $event['title'] }}</div>
            <div class="fs-11 text-muted">
                {{ $activity->triggeredBy?->name ?? __('projects.system') }}
                <span class="mx-1">•</span>
                {{ $activity->created_at?->diffForHumans() }}
            </div>
        </div>
    </div>
@empty
    <div class="text-center text-muted fs-12 py-3">{{ __('projects.no_activity') }}</div>
@endforelse
