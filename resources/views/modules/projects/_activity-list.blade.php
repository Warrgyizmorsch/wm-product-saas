@php
    $groupedActivities = $activities->groupBy(function ($activity) {
        $date = $activity->created_at?->timezone(auth()->user()?->timezone ?? 'UTC') ?? now();
        if ($date->isToday()) {
            return 'Today';
        } elseif ($date->isYesterday()) {
            return 'Yesterday';
        } else {
            return $date->format('j F Y'); // e.g. "10 July 2026"
        }
    });

    $eventMap = [
        'project.created' => [
            'icon' => 'feather-folder',
            'class' => 'border-primary text-primary bg-soft-primary',
            'title' => 'Project Created',
        ],
        'project.updated' => [
            'icon' => 'feather-edit',
            'class' => 'border-info text-info bg-soft-info',
            'title' => 'Project Details Updated',
        ],
        'project.status_changed' => [
            'icon' => 'feather-refresh-cw',
            'class' => 'border-warning text-warning bg-soft-warning',
            'title' => 'Project Status Changed',
        ],
        'project.deleted' => [
            'icon' => 'feather-trash-2',
            'class' => 'border-danger text-danger bg-soft-danger',
            'title' => 'Project Deleted',
        ],
        'milestone.created' => [
            'icon' => 'feather-flag',
            'class' => 'border-secondary text-secondary bg-soft-secondary',
            'title' => 'Milestone Created',
        ],
        'milestone.updated' => [
            'icon' => 'feather-edit',
            'class' => 'border-info text-info bg-soft-info',
            'title' => 'Milestone Updated',
        ],
        'milestone.completed' => [
            'icon' => 'feather-check-circle',
            'class' => 'border-success text-success bg-soft-success',
            'title' => 'Milestone Completed',
        ],
        'milestone.deleted' => [
            'icon' => 'feather-trash-2',
            'class' => 'border-danger text-danger bg-soft-danger',
            'title' => 'Milestone Deleted',
        ],
    ];
@endphp

@if ($activities->isEmpty())
    <div class="text-center py-5 text-muted px-4">
        <div class="avatar-text avatar-lg bg-soft-primary text-primary mx-auto mb-3">
            <i class="feather-activity fs-2"></i>
        </div>
        <h6 class="fw-bold text-dark mb-1">No activity yet</h6>
        <p class="fs-12 text-muted mb-0">Project events will appear here as members, milestones, and other changes occur.</p>
    </div>
@else
    <div class="px-3 pb-3 mb-3 border-bottom d-flex align-items-center justify-content-between">
        <span class="text-muted fs-12 fw-semibold">{{ $activities->count() }} Events</span>
    </div>

    <div class="activity-timeline-container px-3">
        @foreach ($groupedActivities as $dateGroup => $activitiesInGroup)
            <div class="timeline-date-group mb-4">
                <div class="timeline-date-header py-1 mb-3 position-sticky bg-white text-uppercase fs-11 fw-bold text-muted" 
                     style="top: 0; z-index: 10; letter-spacing: 0.05em;">
                    {{ $dateGroup }}
                </div>
                
                <div class="timeline-items position-relative">
                    <div class="timeline-line"></div>
                    
                    @foreach ($activitiesInGroup as $activity)
                        @php
                            $event = $eventMap[$activity->event_type] ?? [
                                'icon' => 'feather-activity',
                                'class' => 'border-secondary text-secondary bg-soft-secondary',
                                'title' => ucwords(str_replace(['.', '_'], ' ', $activity->event_type)),
                            ];

                            // Subject display name
                            $subjectName = null;
                            if ($activity->subject) {
                                $subjectName = $activity->subject->name ?? $activity->subject->title ?? $activity->subject->project_code ?? $activity->subject->code;
                            }
                            
                            // Secondary line priority: Subject Name -> Title -> Description
                            $secondary = $subjectName ?? $activity->title ?? $activity->description;
                            
                            // Relative time
                            $createdAt = $activity->created_at;
                            $timeStr = '';
                            if ($createdAt) {
                                $timezone = auth()->user()?->timezone ?? 'UTC';
                                $localTime = $createdAt->timezone($timezone);
                                
                                if ($createdAt->diffInMinutes() < 60) {
                                    $timeStr = $createdAt->diffForHumans();
                                } elseif ($localTime->isToday()) {
                                    $timeStr = 'Today • ' . $localTime->format('g:i A');
                                } elseif ($localTime->isYesterday()) {
                                    $timeStr = 'Yesterday • ' . $localTime->format('g:i A');
                                } else {
                                    $timeStr = $localTime->format('d/m/Y • g:i A');
                                }
                            }
                        @endphp
                        
                        <div class="timeline-item position-relative">
                            <div class="timeline-icon-dot d-flex align-items-center justify-content-center {{ $event['class'] }}">
                                <i class="{{ $event['icon'] }} fs-12"></i>
                            </div>
                            
                            <div class="timeline-content">
                                <div class="fw-bold text-dark fs-13 lh-sm mb-1 text-wrap text-break">{{ $event['title'] }}</div>
                                
                                @if ($secondary && $secondary !== $event['title'])
                                    <div class="text-secondary fs-12 mb-1 text-wrap text-break">{{ $secondary }}</div>
                                @endif
                                
                                <div class="timeline-metadata text-muted fs-11 text-wrap">
                                    {{ $activity->triggeredBy?->name ?? __('projects.system') }}
                                    <span class="mx-1">•</span>
                                    {{ $timeStr }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif

<style>
    .activity-timeline-container {
        position: relative;
    }
    .timeline-date-group {
        position: relative;
    }
    .timeline-date-header {
        letter-spacing: 0.05em;
    }
    .timeline-items {
        position: relative;
    }
    .timeline-line {
        position: absolute;
        top: 10px;
        bottom: 10px;
        left: 11px;
        width: 2px;
        background-color: #f1f5f9;
        z-index: 1;
    }
    .timeline-item {
        position: relative;
        padding-left: 36px;
        margin-bottom: 24px;
        z-index: 2;
    }
    .timeline-item:last-child {
        margin-bottom: 8px !important;
    }
    .timeline-icon-dot {
        position: absolute;
        left: 0;
        top: 0;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid #e2e8f0;
        z-index: 3;
    }
    .timeline-content {
        padding-left: 4px;
    }
    .timeline-metadata {
        color: #64748b !important;
    }
</style>
