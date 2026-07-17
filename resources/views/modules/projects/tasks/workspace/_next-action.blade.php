@if ($nextAction)
    @php
        $nextActionMeta = [
            'blocked' => ['variant' => 'dark', 'icon' => 'feather-slash'],
            'overdue' => ['variant' => 'danger', 'icon' => 'feather-alert-triangle'],
            'due_today' => ['variant' => 'warning', 'icon' => 'feather-clock'],
            'awaiting_review' => ['variant' => 'info', 'icon' => 'feather-eye'],
            'on_hold' => ['variant' => 'secondary', 'icon' => 'feather-pause-circle'],
        ][$nextAction['state']];

        $overdueDays = (int) ($nextAction['reason'] ?? 0);

        $nextActionMessage = match ($nextAction['state']) {
            'blocked' => $nextAction['reason']
                ? __('projects.next_action_blocked_on', ['task' => $nextAction['reason']])
                : __('projects.next_action_blocked'),
            'overdue' => __('projects.next_action_overdue', [
                'days' => $overdueDays,
                'dayLabel' => $overdueDays === 1 ? 'day' : 'days',
            ]),
            'due_today' => __('projects.next_action_due_today'),
            'awaiting_review' => $nextAction['reason']
                ? __('projects.next_action_awaiting_review_from', ['reviewer' => $nextAction['reason']])
                : __('projects.next_action_awaiting_review'),
            'on_hold' => __('projects.next_action_on_hold'),
        };
    @endphp

    <div class="d-flex align-items-center gap-2 border rounded-3 p-3 mb-4 border-{{ $nextActionMeta['variant'] }}-subtle bg-{{ $nextActionMeta['variant'] }}-subtle bg-opacity-10">
        <i class="{{ $nextActionMeta['icon'] }} text-{{ $nextActionMeta['variant'] }} fs-5"></i>
        <span class="fw-semibold text-dark fs-13">{{ $nextActionMessage }}</span>
    </div>
@endif
