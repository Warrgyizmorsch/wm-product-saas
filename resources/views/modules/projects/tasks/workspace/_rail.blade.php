@php
    if ($canManageTask) {
        $assigneeOptions = $activeMembers->pluck('user.name', 'user_id')->prepend(__('projects.unassigned'), '');
        $reviewerOptions = $assigneeOptions;
    }
@endphp

<div class="border rounded-3 p-3">
    <h6 class="fw-bold text-dark mb-3">{{ __('projects.details') }}</h6>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_assignee') }}</div>
        @if ($canManageTask)
            <x-ui.inline-edit field="assignee_id" :value="$task->assignee_id" :url="route('projects.tasks.field', [$project, $task])" type="select2" :options="$assigneeOptions" :label="__('projects.task_assignee')" />
        @else
            <div class="fs-13 text-dark">{{ $task->assignee?->name ?: __('projects.unassigned') }}</div>
        @endif
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_reviewer') }}</div>
        @if ($canManageTask)
            <x-ui.inline-edit field="reviewer_id" :value="$task->reviewer_id" :url="route('projects.tasks.field', [$project, $task])" type="select2" :options="$reviewerOptions" :label="__('projects.task_reviewer')" />
        @else
            <div class="fs-13 text-dark">{{ $task->reviewer?->name ?: __('projects.unassigned') }}</div>
        @endif
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.start_date') }}</div>
            <div class="fs-13 text-dark">{{ $task->start_date?->format('d/m/Y') ?: '—' }}</div>
        </div>
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.due_date') }}</div>
            @if ($canManageTask)
                <x-ui.inline-edit field="due_date" :value="optional($task->due_date)->format('Y-m-d')" :url="route('projects.tasks.field', [$project, $task])" type="date" :label="__('projects.due_date')" />
            @else
                <div class="fs-13 text-dark">{{ $task->due_date?->format('d/m/Y') ?: '—' }}</div>
            @endif
        </div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.estimated_hours') }}</div>
        <div class="fs-13 text-dark">{{ $task->estimated_hours ?? '—' }}</div>
    </div>
    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.actual_hours', ['default' => 'Actual Hours']) }}</div>
        <div class="fs-13 text-dark font-monospace fw-semibold">{{ number_format((float) ($task->actual_hours ?? 0), 2) }} hrs</div>
    </div>


    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_list') }}</div>
        <div class="fs-13 text-dark">{{ $task->taskList?->name ?: '—' }}</div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.milestone') }}</div>
        <div class="fs-13 text-dark">{{ $task->milestone?->name ?: '—' }}</div>
    </div>

    @if ($project->billing_method === \App\Domains\Projects\Models\Project::BILLING_METHOD_TASK_BASED)
        <div class="pt-3 border-top mt-3">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-2">{{ __('projects.task_billing') }}</div>
            @if ($project->budget_type === \App\Domains\Projects\Models\Project::BUDGET_TYPE_FIXED)
                <div class="mb-2">
                    <div class="fs-11 text-muted fw-semibold mb-1">
                        {{ __('projects.fixed_task_price') }} ({{ active_currency_symbol() }})
                    </div>
                    @if ($canManageTask && !$task->is_invoiced)
                        <x-ui.inline-edit field="billing_amount" :value="$task->billing_amount ?? '0.00'"
                            :url="route('projects.tasks.field', [$project, $task])" type="number"
                            :label="__('projects.fixed_task_price')" />
                    @else
                        <div class="fs-13 text-dark fw-bold font-monospace">
                            {{ format_currency($task->billing_amount ?? 0) }}
                        </div>
                    @endif
                </div>
                <div class="mt-2 fs-11">
                    @if ($task->is_invoiced)
                        <span class="badge bg-success-subtle text-success"><i class="feather-check me-1"></i>{{ __('projects.invoiced') }}</span>
                    @elseif ($task->status === \App\Domains\Projects\Models\Task::STATUS_COMPLETED)
                        <span class="badge bg-warning-subtle text-warning"><i class="feather-alert-circle me-1"></i>{{ __('projects.ready_to_invoice') }}</span>
                    @else
                        <span class="text-muted"><i class="feather-info me-1"></i>{{ __('projects.billable_on_completion') }}</span>
                    @endif
                </div>
            @else
                <div class="mb-2">
                    <span class="badge bg-info-subtle text-info">
                        <i class="feather-clock me-1"></i>{{ __('projects.time_and_material') }}
                    </span>
                </div>
                <div class="fs-11 text-muted">
                    {{ __('projects.task_tm_billing_hint', ['default' => 'Billed based on approved timesheet hours at team member rates.']) }}
                </div>
            @endif
        </div>
    @endif
</div>
