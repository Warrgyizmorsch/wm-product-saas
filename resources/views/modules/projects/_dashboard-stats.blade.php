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
            'tab' => 'summary',
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
            'tab' => 'summary',
        ],
    ];

    if (isset($costSummary)) {
        $mfgCost = (float) ($costSummary['mfg_cost'] ?? 0);
        $timesheetCost = (float) ($costSummary['timesheet_cost'] ?? 0);
        $totalConsumed = (float) ($costSummary['total_consumed'] ?? 0);
        $percent = $costSummary['percent'] ?? null;
        $isOver = $costSummary['is_over_budget'] ?? false;

        $statCards[] = [
            'label' => __('projects.cost_consumed') ?: 'Cost Consumed',
            'value' => format_currency($totalConsumed),
            'is_cost' => true,
            'mfg_cost' => $mfgCost,
            'timesheet_cost' => $timesheetCost,
            'percent' => $percent,
            'tone' => $isOver ? 'danger' : 'purple',
            'tab' => 'summary',
            'anchor' => $mfgCost > 0 ? 'manufacturingSummaryCard' : 'tab-summary',
        ];
    }
@endphp

@push('styles')
    <style>
        .bg-soft-purple {
            background-color: rgba(147, 51, 234, 0.08) !important;
        }
        .border-purple {
            border-color: rgba(147, 51, 234, 0.25) !important;
        }
        .text-purple {
            color: #9333ea !important;
        }
    </style>
@endpush

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    @foreach ($statCards as $stat)
        <div class="col">
            <a href="{{ route('projects.show', $project) }}?tab={{ $stat['tab'] }}#{{ $stat['anchor'] ?? 'tab-' . $stat['tab'] }}" class="text-decoration-none d-block h-100">
                <div class="bg-soft-{{ $stat['tone'] }} rounded p-3 text-center border border-{{ $stat['tone'] }} project-stat-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="text-{{ $stat['tone'] }} fs-11 text-uppercase fw-bold mb-1">{{ $stat['label'] }}</div>
                        <h3 class="text-{{ $stat['tone'] }} fw-bold mb-1 fs-18 text-truncate" title="{{ $stat['value'] }}">{{ $stat['value'] }}</h3>
                    </div>
                    @if(!empty($stat['is_cost']))
                        <div class="fs-11 text-muted mt-1">
                            @if(($stat['mfg_cost'] ?? 0) > 0)
                                <div class="badge bg-soft-primary text-primary border border-primary-subtle px-1.5 py-0.5 fs-10 fw-bold d-inline-flex align-items-center gap-1" title="{{ __('projects.mfg_actual_cost') }}">
                                    <i class="feather-cpu"></i>Mfg: {{ format_currency($stat['mfg_cost']) }}
                                </div>
                            @endif
                            @if(($stat['timesheet_cost'] ?? 0) > 0 && ($stat['mfg_cost'] ?? 0) > 0)
                                <div class="badge bg-soft-secondary text-dark px-1 py-0.5 fs-10 mt-1 d-inline-block">
                                    Labor: {{ format_currency($stat['timesheet_cost']) }}
                                </div>
                            @elseif(($stat['timesheet_cost'] ?? 0) > 0 && ($stat['mfg_cost'] ?? 0) <= 0)
                                <span class="badge bg-soft-secondary text-dark px-1 py-0.5 fs-10">
                                    Labor: {{ format_currency($stat['timesheet_cost']) }}
                                </span>
                            @endif
                            @if($stat['percent'] !== null)
                                <div class="fs-10 text-muted mt-0.5">({{ $stat['percent'] }}% {{ __('projects.of_budget') }})</div>
                            @endif
                        </div>
                    @else
                        <div class="fs-11 text-muted mt-1">{{ $stat['meta'] }}</div>
                    @endif
                </div>
            </a>
        </div>
    @endforeach
</div>

