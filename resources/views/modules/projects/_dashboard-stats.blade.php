@php
    $statCards = [
        [
            'label' => __('projects.total_tasks'),
            'value' => $dashboard['tasks']['total'],
            'meta' => __('projects.tasks_breakdown', [
                'done' => $dashboard['tasks']['done'],
                'progress' => $dashboard['tasks']['in_progress'],
                'todo' => $dashboard['tasks']['todo'],
            ]),
            'tone' => 'primary',
            'tab' => 'tasklists',
        ],
        [
            'label' => __('projects.milestones'),
            'value' => $dashboard['milestones']['total'],
            'meta' => __('projects.milestones_breakdown', [
                'active' => $dashboard['milestones']['active'],
                'completed' => $dashboard['milestones']['completed'],
            ]),
            'tone' => 'success',
            'tab' => 'milestones',
        ],
        [
            'label' => __('projects.members'),
            'value' => $dashboard['members']['total'],
            'meta' => __('projects.members_active', ['count' => $dashboard['members']['active']]),
            'tone' => 'info',
            'tab' => 'summary',
            'anchor' => 'projectCollaborators',
        ],
        [
            'label' => __('projects.hours_tracked'),
            'value' => number_format($dashboard['hours']['tracked'], 1),
            'meta' => $dashboard['hours']['budget'] > 0
                ? __('projects.hours_of_budget', ['budget' => number_format($dashboard['hours']['budget'], 1)])
                : __('projects.no_budget_hours'),
            'tone' => 'warning',
            'tab' => 'tasklists',
        ],
    ];
@endphp

<div class="row g-3 mb-4">
    @foreach ($statCards as $stat)
        <div class="col-md-3">
            <a href="{{ route('projects.show', $project) }}?tab={{ $stat['tab'] }}#{{ $stat['anchor'] ?? 'tab-' . $stat['tab'] }}" class="text-decoration-none d-block">
                <div class="bg-soft-{{ $stat['tone'] }} rounded p-3 text-center border border-{{ $stat['tone'] }} project-stat-card">
                    <div class="text-{{ $stat['tone'] }} fs-11 text-uppercase fw-bold mb-1">{{ $stat['label'] }}</div>
                    <h3 class="text-{{ $stat['tone'] }} fw-bold mb-1">{{ $stat['value'] }}</h3>
                    <div class="fs-11 text-muted">{{ $stat['meta'] }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>
