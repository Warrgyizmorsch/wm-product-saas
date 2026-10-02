@extends('layouts.duralux')

@section('title', (__('projects.report_task_status_name') ?: 'Task Status Report') . ' | SaaS ERP')
@section('page-title', __('projects.report_task_status_name') ?: 'Task Status Report')
@section('breadcrumb', (__('projects.reports') ?: 'Reports') . ' / ' . (__('projects.report_task_status_name') ?: 'Task Status'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.reports.task-status') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.reports.task-status')">
                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('projects.filter_options') ?: 'Filter Options' }}</h6>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.project') ?: 'Project' }}</label>
                    <x-ui.odoo-form-ui type="select" name="project_id">
                        <option value="">{{ __('projects.all_projects') ?: 'All Projects' }}</option>
                        @foreach ($projects as $proj)
                            <option value="{{ $proj->id }}" @selected($filters->project_id == $proj->id)>{{ $proj->project_code }} - {{ $proj->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.assignee') ?: 'Assignee' }}</label>
                    <x-ui.odoo-form-ui type="select" name="user_id">
                        <option value="">{{ __('projects.all_assignees') ?: 'All Assignees' }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($filters->user_id == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') ?: 'Status' }}</label>
                    <x-ui.odoo-form-ui type="select" name="status">
                        <option value="">{{ __('projects.all_statuses') ?: 'All Statuses' }}</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected($filters->status === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.priority') ?: 'Priority' }}</label>
                    <x-ui.odoo-form-ui type="select" name="priority">
                        <option value="">{{ __('projects.all_priorities') ?: 'All Priorities' }}</option>
                        @foreach ($priorities as $pr)
                            <option value="{{ $pr }}" @selected($filters->priority === $pr)>{{ ucfirst($pr) }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
            </x-ui.filter>
        </form>

        <div class="dropdown">
            <x-ui.button variant="light-brand" class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" icon="feather-download">
                {{ __('projects.export') ?: 'Export' }}
            </x-ui.button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'task-status', 'format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'task-status', 'format' => 'csv'] + request()->query()) }}">
                        <i class="feather-file-text me-2 text-primary"></i>{{ __('projects.export_csv') ?: 'CSV (.csv)' }}
                    </a>
                </li>
            </ul>
        </div>
        <x-ui.button href="{{ route('projects.reports.index') }}" variant="light" icon="feather-arrow-left">
            {{ __('projects.back_to_reports') ?: 'Reports' }}
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border">
    {{-- Report Table --}}
    <x-ui.odoo-form-ui type="table" hoverable responsive>
        <thead>
            <tr class="fs-11 text-uppercase text-muted border-bottom">
                <th class="ps-3">{{ __('projects.task_number') ?: 'Task #' }}</th>
                <th>{{ __('projects.task_title') ?: 'Title' }}</th>
                <th>{{ __('projects.project') ?: 'Project' }}</th>
                <th>{{ __('projects.milestone') ?: 'Milestone' }}</th>
                <th>{{ __('projects.assignee') ?: 'Assignee' }}</th>
                <th>{{ __('projects.priority') ?: 'Priority' }}</th>
                <th>{{ __('projects.status') ?: 'Status' }}</th>
                <th>{{ __('projects.due_date') ?: 'Due Date' }}</th>
                <th class="text-end">{{ __('projects.estimated_hours') ?: 'Est (h)' }}</th>
                <th class="text-end">{{ __('projects.actual_hours') ?: 'Actual (h)' }}</th>
                <th class="text-end pe-3">{{ __('projects.variance') ?: 'Variance' }}</th>
            </tr>
        </thead>
        <tbody class="fs-13">
            @forelse ($tasks as $t)
                @php
                    $isOverdue = $t->due_date && \Carbon\Carbon::parse($t->due_date)->lt(\Carbon\Carbon::today()) && !in_array($t->status, ['Completed', 'Cancelled'], true);
                    $est = (float) ($t->estimated_hours ?? 0);
                    $act = (float) ($t->actual_hours ?? 0);
                    $var = $act - $est;
                @endphp
                <tr>
                    <td class="ps-3 fw-bold font-monospace">
                        <a href="{{ route('projects.tasks.show', [$t->project_id, $t->id]) }}" class="text-primary text-decoration-none">
                            {{ $t->task_code ?? $t->task_number }}
                        </a>
                    </td>
                    <td class="fw-semibold text-dark">{{ $t->title }}</td>
                    <td class="text-muted">{{ $t->project?->name ?? '—' }}</td>
                    <td class="text-muted">{{ $t->milestone?->name ?? '—' }}</td>
                    <td>{{ $t->assignee?->name ?? '—' }}</td>
                    <td>
                        <x-ui.badge variant="{{ $t->priority === 'Urgent' ? 'danger' : ($t->priority === 'High' ? 'warning' : 'info') }}" soft>
                            {{ ucfirst($t->priority) }}
                        </x-ui.badge>
                    </td>
                    <td>
                        <x-ui.badge variant="{{ $t->status === 'Completed' ? 'success' : ($t->status === 'In Progress' ? 'primary' : 'secondary') }}" soft>
                            {{ ucfirst($t->status) }}
                        </x-ui.badge>
                    </td>
                    <td class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">
                        {{ $t->due_date ? \Carbon\Carbon::parse($t->due_date)->format('d M Y') : '—' }}
                        @if ($isOverdue)
                            <span class="badge bg-soft-danger text-danger fs-10 ms-1">{{ __('projects.overdue') ?: 'Overdue' }}</span>
                        @endif
                    </td>
                    <td class="text-end">{{ number_format($est, 1) }}h</td>
                    <td class="text-end fw-semibold">{{ number_format($act, 1) }}h</td>
                    <td class="text-end pe-3 {{ $var > 0 ? 'text-danger' : ($var < 0 ? 'text-success' : 'text-muted') }}">
                        {{ $var > 0 ? '+' : '' }}{{ number_format($var, 1) }}h
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        {{ __('projects.no_tasks_found') ?: 'No tasks found matching criteria.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <div class="mt-4">
        {{ $tasks->links() }}
    </div>
</div>
@endsection
