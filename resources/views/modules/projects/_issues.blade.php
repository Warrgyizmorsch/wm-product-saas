@php
    $issueSearch = trim((string) request('search', ''));
    $issueStatusFilter = (string) request('status', '');
    $issueSeverityFilter = (string) request('severity', '');
    $issuePriorityFilter = (string) request('priority', '');
    $issueAssigneeFilter = (string) request('assignee_id', '');

    $hasActiveIssueFilters = $issueSearch !== ''
        || $issueStatusFilter !== ''
        || $issueSeverityFilter !== ''
        || $issuePriorityFilter !== ''
        || $issueAssigneeFilter !== '';

    $filteredIssues = $issues->filter(function ($issue) use (
        $issueSearch,
        $issueStatusFilter,
        $issueSeverityFilter,
        $issuePriorityFilter,
        $issueAssigneeFilter
    ) {
        if ($issueSearch !== '') {
            $haystack = strtolower($issue->title . ' ' . $issue->issue_number . ' ' . $issue->description);
            if (!str_contains($haystack, strtolower($issueSearch))) {
                return false;
            }
        }

        if ($issueStatusFilter !== '' && $issue->status !== $issueStatusFilter) {
            return false;
        }

        if ($issueSeverityFilter !== '' && $issue->severity !== $issueSeverityFilter) {
            return false;
        }

        if ($issuePriorityFilter !== '' && $issue->priority !== $issuePriorityFilter) {
            return false;
        }

        if ($issueAssigneeFilter !== '' && (string) $issue->assignee_id !== $issueAssigneeFilter) {
            return false;
        }

        return true;
    })->values();

    $issueAssigneeOptions = $issues->pluck('assignee')->filter()->unique('id')->sortBy(fn ($user) => $user->name)->values();
    $issuePage = (int) request('issue_page', 1);
    $issuesPerPage = 10;
    $totalFilteredIssues = $filteredIssues->count();
    $totalIssuePages = (int) ceil($totalFilteredIssues / $issuesPerPage);
    $paginatedIssues = $filteredIssues->slice(($issuePage - 1) * $issuesPerPage, $issuesPerPage);
@endphp

{{-- Toolbar --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <form method="GET" action="{{ route('projects.show', $project) }}" class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="tab" value="issues">
        <div style="min-width: 220px;">
            <input type="text" name="search" class="form-control form-control-sm" value="{{ $issueSearch }}"
                   placeholder="{{ __('projects.search_placeholder') }}">
        </div>

        <button type="submit" class="btn btn-primary btn-sm">
            <i class="feather-search me-1"></i>{{ __('ui.search') }}
        </button>

        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i>{{ __('projects.filter_options') }}</h6>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') }}</label>
                <x-ui.odoo-form-ui type="select" name="status">
                    <option value="">{{ __('projects.all_statuses') }}</option>
                    @foreach (\App\Domains\Projects\Models\Issue::STATUSES as $statusOption)
                        <option value="{{ $statusOption }}" @selected($issueStatusFilter === $statusOption)>
                            {{ __('projects.issue_statuses.' . $statusOption) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.severity') }}</label>
                <x-ui.odoo-form-ui type="select" name="severity">
                    <option value="">{{ __('projects.all_severities') }}</option>
                    @foreach (\App\Domains\Projects\Models\Issue::SEVERITIES as $sevOption)
                        <option value="{{ $sevOption }}" @selected($issueSeverityFilter === $sevOption)>
                            {{ __('projects.severities.' . $sevOption) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.priority') }}</label>
                <x-ui.odoo-form-ui type="select" name="priority">
                    <option value="">{{ __('projects.all_priorities') }}</option>
                    @foreach (\App\Domains\Projects\Models\Issue::PRIORITIES as $prioOption)
                        <option value="{{ $prioOption }}" @selected($issuePriorityFilter === $prioOption)>
                            {{ __('projects.priorities.' . $prioOption) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.assignee') }}</label>
                <x-ui.odoo-form-ui type="select" name="assignee_id" select2Selector="default">
                    <option value="">{{ __('projects.all_assignees') }}</option>
                    @foreach ($issueAssigneeOptions as $assignee)
                        <option value="{{ $assignee->id }}" @selected($issueAssigneeFilter === (string) $assignee->id)>
                            {{ $assignee->name }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'issues']) }}" class="btn btn-sm btn-light border">{{ __('projects.reset') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.apply_filters') }}</button>
            </div>
        </x-ui.filter>
    </form>

    @if ($canCreateIssues)
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createIssueModal">
            <i class="feather-plus me-1"></i>{{ __('projects.report_issue') }}
        </button>
    @endif
</div>

{{-- Issues Table --}}
<div class="border rounded bg-white">
    <x-ui.odoo-form-ui type="table" tableClass="table table-hover align-middle mb-0">
        <thead>
            <tr class="text-muted fs-11 text-uppercase border-bottom">
                <th style="width: 140px;">{{ __('projects.issue_code') }}</th>
                <th>{{ __('projects.issue_title') }}</th>
                <th style="width: 110px;">{{ __('projects.severity') }}</th>
                <th style="width: 110px;">{{ __('projects.priority') }}</th>
                <th style="width: 120px;">{{ __('projects.status') }}</th>
                <th style="width: 160px;">{{ __('projects.assignee') }}</th>
                <th style="width: 140px;">{{ __('projects.reporter') }}</th>
                <th style="width: 80px;" class="text-end">{{ __('projects.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paginatedIssues as $issue)
                @php
                    $sevBadgeClass = match($issue->severity) {
                        'Critical', 'critical' => 'badge bg-danger-subtle text-danger border border-danger-subtle',
                        'Major', 'major' => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                        'Minor', 'minor' => 'badge bg-secondary-subtle text-secondary border border-secondary-subtle',
                        default => 'badge bg-secondary-subtle text-secondary',
                    };
                    $prioBadgeClass = match($issue->priority) {
                        'Critical', 'urgent', 'critical' => 'badge bg-danger text-white',
                        'High', 'high' => 'badge bg-warning text-dark',
                        'Medium', 'medium' => 'badge bg-primary-subtle text-primary border border-primary-subtle',
                        'Low', 'low' => 'badge bg-light text-muted border',
                        default => 'badge bg-light text-muted',
                    };
                    $statusBadgeClass = match($issue->status) {
                        'Open' => 'badge bg-secondary-subtle text-secondary border',
                        'Assigned' => 'badge bg-info-subtle text-info border border-info-subtle',
                        'In Progress' => 'badge bg-primary-subtle text-primary border border-primary-subtle',
                        'Resolved' => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                        'Closed' => 'badge bg-success-subtle text-success border border-success-subtle',
                        default => 'badge bg-secondary-subtle text-secondary',
                    };
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('projects.issues.show', [$project, $issue]) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                            {{ $issue->issue_code }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('projects.issues.show', [$project, $issue]) }}" class="fw-semibold text-dark text-decoration-none d-block">
                            {{ $issue->title }}
                        </a>
                        @if ($issue->task)
                            <span class="fs-11 text-muted">
                                <i class="feather-check-square me-1"></i>{{ $issue->task->task_number }} - {{ $issue->task->title }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="{{ $sevBadgeClass }} fs-11">
                            {{ __('projects.severities.' . $issue->severity) }}
                        </span>
                    </td>
                    <td>
                        <span class="{{ $prioBadgeClass }} fs-11">
                            {{ __('projects.priorities.' . $issue->priority) }}
                        </span>
                    </td>
                    <td>
                        <span class="{{ $statusBadgeClass }} fs-11">
                            {{ __('projects.issue_statuses.' . $issue->status) }}
                        </span>
                    </td>
                    <td>
                        @if ($issue->assignee)
                            <div class="d-flex align-items-center gap-1">
                                <span class="avatar-circle-sm bg-primary-subtle text-primary fw-bold" style="width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px;">
                                    {{ strtoupper(substr($issue->assignee->name, 0, 2)) }}
                                </span>
                                <span class="fs-12 text-dark">{{ $issue->assignee->name }}</span>
                            </div>
                        @else
                            <span class="text-muted fs-12">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="fs-12 text-muted">{{ $issue->reporter?->name ?: '—' }}</span>
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-icon btn-light" type="button" data-bs-toggle="dropdown">
                                <i class="feather-more-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <a class="dropdown-item fs-12" href="{{ route('projects.issues.show', [$project, $issue]) }}">
                                        <i class="feather-eye me-2 text-primary"></i>{{ __('projects.view') }}
                                    </a>
                                </li>
                                @can('delete', $issue)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('projects.issues.destroy', [$project, $issue]) }}" onsubmit="return confirm('{{ __('projects.confirm_delete_issue') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item fs-12 text-danger">
                                                <i class="feather-trash-2 me-2"></i>{{ __('projects.delete') }}
                                            </button>
                                        </form>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="feather-alert-circle fs-2 d-block mb-2 text-muted"></i>
                        <div class="fw-semibold">{{ __('projects.no_issues_found') }}</div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    @if ($totalIssuePages > 1)
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <span class="fs-12 text-muted">
                {{ __('projects.showing_entries', ['first' => (($issuePage - 1) * $issuesPerPage) + 1, 'last' => min($issuePage * $issuesPerPage, $totalFilteredIssues), 'total' => $totalFilteredIssues]) }}
            </span>
            <div class="d-flex gap-1">
                @if ($issuePage > 1)
                    <a href="{{ route('projects.show', array_merge(request()->query(), ['project' => $project, 'tab' => 'issues', 'issue_page' => $issuePage - 1])) }}" class="btn btn-sm btn-light border">
                        &laquo;
                    </a>
                @endif
                <span class="btn btn-sm btn-light border disabled">{{ $issuePage }} / {{ $totalIssuePages }}</span>
                @if ($issuePage < $totalIssuePages)
                    <a href="{{ route('projects.show', array_merge(request()->query(), ['project' => $project, 'tab' => 'issues', 'issue_page' => $issuePage + 1])) }}" class="btn btn-sm btn-light border">
                        &raquo;
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
