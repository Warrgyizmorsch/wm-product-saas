@props([
    'task' => null,
    'title' => null,
    'completed' => false,
    'status' => 'in_progress',
    'priority' => 'medium',
    'dueDate' => null,
    'labels' => [],
    'progress' => null,
    'users' => [],
    'attachmentCount' => 0,
    'commentCount' => 0,
    'estimatedTime' => null,
    'variant' => 'kanban', // kanban, list
])

@php
    if ($task) {
        $t = is_array($task) ? $task : $task->toArray();
        $title = $title ?? ($t['title'] ?? $t['name'] ?? 'Untitled Task');
        $completed = $completed || ($t['completed'] ?? $t['is_completed'] ?? false);
        $status = $status !== 'in_progress' ? $status : ($t['status'] ?? 'in_progress');
        $priority = $priority !== 'medium' ? $priority : ($t['priority'] ?? 'medium');
        $dueDate = $dueDate ?? ($t['due_date'] ?? null);
        $labels = !empty($labels) ? $labels : ($t['labels'] ?? $t['tags'] ?? []);
        $progress = $progress !== null ? $progress : ($t['progress'] ?? null);
        $users = !empty($users) ? $users : ($t['assignees'] ?? $t['users'] ?? []);
        $attachmentCount = $attachmentCount ?: ($t['attachments_count'] ?? $t['attachment_count'] ?? 0);
        $commentCount = $commentCount ?: ($t['comments_count'] ?? $t['comment_count'] ?? 0);
        $estimatedTime = $estimatedTime ?? ($t['estimated_time'] ?? $t['estimate'] ?? null);
    }
@endphp

@if ($variant === 'list')
    <div {{ $attributes->merge(['class' => 'card border-0 shadow-xs mb-2 p-3 hover-shadow-sm transition-all']) }}>
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 flex-grow-1">
                <div class="form-check mb-0">
                    <input class="form-check-input task-checkbox" type="checkbox" {{ $completed ? 'checked' : '' }}>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-0 fs-14 fw-semibold text-dark {{ $completed ? 'text-decoration-line-through text-muted' : '' }}">
                        {{ $title }}
                    </h6>
                    @if (!empty($labels))
                        <div class="d-flex align-items-center gap-1 mt-1">
                            @foreach ($labels as $label)
                                <span class="badge bg-soft-secondary text-secondary px-1.5 py-0.5 fs-10">{{ $label }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <x-ui.priority-badge :priority="$priority" />
                <x-ui.status-badge :status="$status" size="sm" />

                @if ($dueDate)
                    <span class="fs-12 text-muted" title="{{ __('Due Date') }}">
                        <i class="feather-calendar me-1"></i>{{ $dueDate }}
                    </span>
                @endif

                <x-ui.avatar-group :users="$users" size="xs" :max="2" />

                @if (isset($actions))
                    <div class="dropdown">
                        <a href="javascript:void(0)" class="avatar-text avatar-xs text-muted" data-bs-toggle="dropdown">
                            <i class="feather-more-vertical fs-12"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            {{ $actions }}
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
@else
    <!-- Kanban Grid Variant -->
    <div {{ $attributes->merge(['class' => 'card border-0 shadow-sm mb-3 p-3 position-relative']) }}>
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <div class="d-flex align-items-center gap-2">
                <input class="form-check-input task-checkbox mt-0" type="checkbox" {{ $completed ? 'checked' : '' }}>
                <x-ui.priority-badge :priority="$priority" />
            </div>

            @if (isset($actions))
                <div class="dropdown">
                    <a href="javascript:void(0)" class="avatar-text avatar-xs text-muted rounded-circle" data-bs-toggle="dropdown">
                        <i class="feather-more-vertical fs-13"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        {{ $actions }}
                    </ul>
                </div>
            @endif
        </div>

        <h6 class="fs-14 fw-bold text-dark mb-2 {{ $completed ? 'text-decoration-line-through text-muted' : '' }}">
            {{ $title }}
        </h6>

        @if (!empty($labels))
            <div class="d-flex flex-wrap gap-1 mb-3">
                @foreach ($labels as $label)
                    <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-10 fw-medium">{{ $label }}</span>
                @endforeach
            </div>
        @endif

        @if ($progress !== null)
            <div class="mb-3">
                <x-ui.progress-bar :value="$progress" height="4px" />
            </div>
        @endif

        <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-auto">
            <div class="d-flex align-items-center gap-3 fs-11 text-muted">
                @if ($dueDate)
                    <span title="{{ __('Due Date') }}">
                        <i class="feather-clock me-1 text-warning"></i>{{ $dueDate }}
                    </span>
                @endif

                @if ($commentCount > 0)
                    <span title="{{ __('Comments') }}">
                        <i class="feather-message-square me-1"></i>{{ $commentCount }}
                    </span>
                @endif

                @if ($attachmentCount > 0)
                    <span title="{{ __('Attachments') }}">
                        <i class="feather-paperclip me-1"></i>{{ $attachmentCount }}
                    </span>
                @endif
            </div>

            <div>
                <x-ui.avatar-group :users="$users" size="xs" :max="3" />
            </div>
        </div>
    </div>
@endif
