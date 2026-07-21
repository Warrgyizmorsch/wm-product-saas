@php
    $overviewOpenTasks = max($totalTasks - $completedTasks, 0);
    $overviewOverdueCount = $attentionTasks->where('attention_reason', 'overdue')->count();
    $overviewBlockedCount = $attentionTasks->where('attention_reason', 'blocked')->count();
    $overviewDueSoonCount = $attentionTasks->where('attention_reason', 'due_soon')->count();

    $attentionReasonMeta = [
        'overdue' => ['label' => __('projects.attention_overdue'), 'variant' => 'danger', 'icon' => 'feather-alert-triangle'],
        'blocked' => ['label' => __('projects.attention_blocked'), 'variant' => 'dark', 'icon' => 'feather-slash'],
        'due_soon' => ['label' => __('projects.attention_due_soon'), 'variant' => 'warning', 'icon' => 'feather-clock'],
    ];
@endphp

{{-- Quick statistics --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="border rounded-3 p-3 text-center h-100 project-stat-card">
            <div class="fs-20 fw-bold text-dark">{{ $taskLists->count() }}</div>
            <div class="fs-11 text-uppercase text-muted fw-semibold">{{ __('projects.tasklists') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded-3 p-3 text-center h-100 project-stat-card">
            <div class="fs-20 fw-bold text-dark">{{ $overviewOpenTasks }}</div>
            <div class="fs-11 text-uppercase text-muted fw-semibold">{{ __('projects.workspace_open_tasks') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded-3 p-3 text-center h-100 project-stat-card">
            <div class="fs-20 fw-bold text-danger">{{ $overviewOverdueCount }}</div>
            <div class="fs-11 text-uppercase text-muted fw-semibold">{{ __('projects.kpi_overdue') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded-3 p-3 text-center h-100 project-stat-card">
            <div class="fs-20 fw-bold text-dark">{{ $milestone->completion_percentage }}%</div>
            <div class="fs-11 text-uppercase text-muted fw-semibold">{{ __('projects.completion_percentage') }}</div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Left column --}}
    <div class="col-lg-6">
        <div class="p-3 mb-4 project-content-card">
            <h6 class="fw-bold text-dark mb-3"><i class="feather-list me-2 text-primary"></i>{{ __('projects.workspace_tasklists_summary') }}</h6>
            @forelse ($taskLists as $summaryTaskList)
                @php $summaryStats = $dashboard['task_lists'][$summaryTaskList->id] ?? ['total' => 0, 'done' => 0, 'percent' => 0]; @endphp
                <div class="d-flex align-items-center justify-content-between gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="fs-13 fw-semibold text-dark text-truncate">{{ $summaryTaskList->name }}</div>
                        <div class="progress mt-1" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: {{ $summaryStats['percent'] }}%"></div>
                        </div>
                    </div>
                    <span class="fs-11 text-muted flex-shrink-0">{{ $summaryStats['done'] }} / {{ $summaryStats['total'] }}</span>
                </div>
            @empty
                <p class="fs-12 text-muted mb-0">{{ __('projects.no_tasklists') }}</p>
            @endforelse
        </div>

        <div class="p-3 project-content-card">
            <h6 class="fw-bold text-dark mb-3"><i class="feather-arrow-up-right me-2 text-primary"></i>{{ __('projects.workspace_upcoming_work') }}</h6>
            @forelse ($upcomingTasks as $upcomingTask)
                <div class="d-flex align-items-center justify-content-between gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="flex-grow-1 text-truncate">
                        <div class="fs-13 text-dark text-truncate">{{ $upcomingTask->title }}</div>
                        <div class="fs-11 text-muted">{{ $upcomingTask->assignee?->name ?? __('projects.unassigned') }}</div>
                    </div>
                    <span class="fs-11 text-muted flex-shrink-0">{{ $upcomingTask->due_date?->format('d/m/Y') }}</span>
                </div>
            @empty
                <p class="fs-12 text-muted mb-0">{{ __('projects.workspace_no_upcoming_work') }}</p>
            @endforelse
        </div>
    </div>

    {{-- Right column --}}
    <div class="col-lg-6">
        <div class="p-3 mb-4 project-content-card">
            <h6 class="fw-bold text-dark mb-3"><i class="feather-alert-triangle me-2 text-danger"></i>{{ __('projects.workspace_attention_required') }}</h6>
            @forelse ($attentionTasks as $attentionTask)
                @php $reasonMeta = $attentionReasonMeta[$attentionTask->attention_reason]; @endphp
                <div class="d-flex align-items-center justify-content-between gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex align-items-center gap-2 flex-grow-1" style="min-width: 0;">
                        <i class="{{ $reasonMeta['icon'] }} text-{{ $reasonMeta['variant'] }}"></i>
                        <div class="text-truncate">
                            <div class="fs-13 text-dark text-truncate">{{ $attentionTask->title }}</div>
                            <div class="fs-11 text-muted">{{ $attentionTask->taskList?->name }}</div>
                        </div>
                    </div>
                    <x-ui.badge variant="{{ $reasonMeta['variant'] }}" soft class="flex-shrink-0">{{ $reasonMeta['label'] }}</x-ui.badge>
                </div>
            @empty
                <p class="fs-12 text-muted mb-0">{{ __('projects.workspace_no_attention_needed') }}</p>
            @endforelse
        </div>

        <div class="p-3 project-content-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="feather-activity me-2 text-primary"></i>{{ __('projects.workspace_recent_activity') }}</h6>
                <a href="javascript:void(0);" onclick="document.getElementById('tab-activity-tab').click();" class="fs-11 fw-semibold">{{ __('projects.view_all') }}</a>
            </div>
            @include('modules.projects._activity-list', ['activities' => $recentActivities])
        </div>
    </div>
</div>
