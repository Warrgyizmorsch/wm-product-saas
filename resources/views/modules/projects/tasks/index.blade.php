@extends('layouts.duralux')

@section('title', __('projects.tasks') . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', __('projects.tasks'))
@section('breadcrumb', __('projects.title') . ' / ' . __('projects.tasks'))

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border">

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-check-square me-2 text-primary"></i>{{ __('projects.tasks') }}
            </h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('projects.tasks.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('projects.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('projects.task_search_placeholder') }}" value="{{ $filters['search'] ?? '' }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('projects.all_statuses') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                        {{ __('projects.task_statuses.' . $status) ?? $status }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.priority') }}</label>
                            <x-ui.odoo-form-ui type="select" name="priority">
                                <option value="">{{ __('projects.all_priorities') }}</option>
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>
                                        {{ __('projects.task_priorities.' . $priority) ?? $priority }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.project') }}</label>
                            <x-ui.odoo-form-ui type="select" name="project_id" select2Selector="default">
                                <option value="">{{ __('projects.all_projects') }}</option>
                                @foreach ($projects as $proj)
                                    <option value="{{ $proj->id }}" @selected(($filters['project_id'] ?? '') == $proj->id)>
                                        [{{ $proj->project_code }}] {{ $proj->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.task_assignee') }}</label>
                            <x-ui.odoo-form-ui type="select" name="assignee_id" select2Selector="default">
                                <option value="">{{ __('projects.all_assignees') }}</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected(($filters['assignee_id'] ?? '') == $assignee->id)>
                                        {{ $assignee->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('projects.tasks.index') }}" class="btn btn-sm btn-light border">{{ __('projects.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="min-width: 240px;">{{ __('projects.task_name') }}</th>
                        <th style="min-width: 180px;">{{ __('projects.project') }}</th>
                        <th style="min-width: 160px;">{{ __('projects.milestone') }}</th>
                        <th>{{ __('projects.task_assignee') }}</th>
                        <th>{{ __('projects.priority') }}</th>
                        <th>{{ __('projects.status') }}</th>
                        <th>{{ __('projects.due_date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $task)
                        @php
                            $statusVariant = match ($task->status) {
                                'Open' => 'secondary',
                                'In Progress' => 'primary',
                                'Review' => 'warning',
                                'On Hold' => 'info',
                                'Completed' => 'success',
                                'Cancelled' => 'danger',
                                default => 'secondary',
                            };
                            $priorityVariant = match ($task->priority) {
                                'Low' => 'secondary',
                                'Medium' => 'info',
                                'High' => 'warning',
                                'Critical' => 'danger',
                                default => 'secondary',
                            };
                        @endphp
                        <tr class="cursor-pointer" onclick="window.location='{{ route('projects.tasks.show', [$task->project, $task]) }}'">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-muted border fs-11 font-monospace">{{ $task->task_code }}</span>
                                    <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="fw-semibold text-dark text-truncate text-decoration-none" onclick="event.stopPropagation();">
                                        {{ $task->title }}
                                    </a>
                                </div>
                                @if ($task->taskList)
                                    <div class="fs-11 text-muted ms-1 mt-1">
                                        <i class="feather-list me-1"></i>{{ $task->taskList->name }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($task->project)
                                    <a href="{{ route('projects.show', $task->project) }}" class="fw-semibold text-primary hover-primary" onclick="event.stopPropagation();">
                                        {{ $task->project->name }}
                                    </a>
                                    <div class="fs-11 text-muted">{{ $task->project->project_code }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($task->milestone)
                                    <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-11">
                                        <i class="feather-flag me-1"></i>{{ $task->milestone->name }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border fs-11">
                                        {{ __('projects.no_milestone') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($task->assignee)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-xs rounded-circle bg-soft-primary text-primary fs-11">
                                            {{ strtoupper(substr($task->assignee->name, 0, 1)) }}
                                        </div>
                                        <span class="fs-12 text-dark">{{ $task->assignee->name }}</span>
                                    </div>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                <x-ui.badge variant="{{ $priorityVariant }}" soft>
                                    {{ __('projects.task_priorities.' . ($task->priority ?: 'Medium')) ?? $task->priority }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <x-ui.badge variant="{{ $statusVariant }}" soft>
                                    {{ __('projects.task_statuses.' . ($task->status ?: 'Open')) ?? $task->status }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <span class="fs-12 {{ $task->due_date && $task->due_date->isPast() && !in_array($task->status, ['Completed', 'Cancelled']) ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    {{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <div class="avatar-text avatar-md bg-soft-primary text-primary mx-auto mb-2">
                                    <i class="feather-check-square fs-3"></i>
                                </div>
                                <div class="fw-semibold text-dark mb-1">{{ __('projects.no_tasks_found') }}</div>
                                <div class="fs-12 text-muted">{{ __('projects.no_matching_tasks_hint') }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <x-ui.pagination
            :currentPage="$tasks->currentPage()"
            :lastPage="$tasks->lastPage()"
            :total="$tasks->total()"
            :perPage="$tasks->perPage()"
            :from="$tasks->firstItem()"
            :to="$tasks->lastItem()"
        />

    </div>
@endsection
