@extends('layouts.duralux')

@section('title', (__('projects.report_issues_name') ?: 'Issue & Defect Density Report') . ' | SaaS ERP')
@section('page-title', __('projects.report_issues_name') ?: 'Issue & Defect Density Report')
@section('breadcrumb', (__('projects.reports') ?: 'Reports') . ' / ' . (__('projects.report_issues_name') ?: 'Issue Density'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.reports.issue-defect-density') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.reports.issue-defect-density')">
                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('projects.filter_options') ?: 'Filter Options' }}</h6>
                <div class="mb-3">
                    <x-ui.odoo-form-ui type="select" name="project_id" label="{{ __('projects.project') ?: 'Project' }}">
                        <option value="">{{ __('projects.all_projects') ?: 'All Projects' }}</option>
                        @foreach ($projects as $proj)
                            <option value="{{ $proj->id }}" @selected($filters->project_id == $proj->id)>{{ $proj->project_code }} - {{ $proj->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="mb-3">
                    <x-ui.odoo-form-ui type="select" name="severity" label="{{ __('projects.severity') ?: 'Severity' }}">
                        <option value="">{{ __('projects.all_severities') ?: 'All Severities' }}</option>
                        @foreach ($severities as $sev)
                            <option value="{{ $sev }}" @selected($filters->severity === $sev)>{{ ucfirst($sev) }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="mb-3">
                    <x-ui.odoo-form-ui type="select" name="priority" label="{{ __('projects.priority') ?: 'Priority' }}">
                        <option value="">{{ __('projects.all_priorities') ?: 'All Priorities' }}</option>
                        @foreach ($priorities as $pr)
                            <option value="{{ $pr }}" @selected($filters->priority === $pr)>{{ ucfirst($pr) }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="mb-3">
                    <x-ui.odoo-form-ui type="select" name="status" label="{{ __('projects.status') ?: 'Status' }}">
                        <option value="">{{ __('projects.all_statuses') ?: 'All Statuses' }}</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected($filters->status === $st)>{{ ucfirst($st) }}</option>
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
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'issue-defect-density', 'format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'issue-defect-density', 'format' => 'csv'] + request()->query()) }}">
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
                <th class="ps-3">{{ __('projects.issue_number') ?: 'Issue #' }}</th>
                <th>{{ __('projects.issue_title') ?: 'Title' }}</th>
                <th>{{ __('projects.project') ?: 'Project' }}</th>
                <th>{{ __('projects.severity') ?: 'Severity' }}</th>
                <th>{{ __('projects.priority') ?: 'Priority' }}</th>
                <th>{{ __('projects.status') ?: 'Status' }}</th>
                <th>{{ __('projects.assignee') ?: 'Assignee' }}</th>
                <th>{{ __('projects.reporter') ?: 'Reporter' }}</th>
                <th>{{ __('projects.logged_date') ?: 'Logged' }}</th>
                <th>{{ __('projects.resolution_date') ?: 'Resolved' }}</th>
                <th class="text-end pe-3">{{ __('projects.days_to_resolve') ?: 'Days' }}</th>
            </tr>
        </thead>
        <tbody class="fs-13">
            @forelse ($issues as $i)
                @php
                    $days = $i->resolution_date
                        ? $i->created_at->diffInDays(\Carbon\Carbon::parse($i->resolution_date))
                        : $i->created_at->diffInDays(now());
                @endphp
                <tr>
                    <td class="ps-3 fw-bold font-monospace">
                        <a href="{{ route('projects.issues.show', [$i->project_id, $i->id]) }}" class="text-primary text-decoration-none">
                            {{ $i->issue_number }}
                        </a>
                    </td>
                    <td class="fw-semibold text-dark">{{ $i->title }}</td>
                    <td class="text-muted">{{ $i->project?->name ?? '—' }}</td>
                    <td>
                        <x-ui.badge variant="{{ $i->severity === 'Critical' ? 'danger' : ($i->severity === 'High' ? 'warning' : 'info') }}" soft>
                            {{ ucfirst($i->severity) }}
                        </x-ui.badge>
                    </td>
                    <td>
                        <x-ui.badge variant="{{ $i->priority === 'Urgent' ? 'danger' : ($i->priority === 'High' ? 'warning' : 'secondary') }}" soft>
                            {{ ucfirst($i->priority) }}
                        </x-ui.badge>
                    </td>
                    <td>
                        <x-ui.badge variant="{{ $i->status === 'Closed' ? 'success' : ($i->status === 'Resolved' ? 'info' : 'secondary') }}" soft>
                            {{ ucfirst($i->status) }}
                        </x-ui.badge>
                    </td>
                    <td>{{ $i->assignee?->name ?? '—' }}</td>
                    <td class="text-muted">{{ $i->reporter?->name ?? '—' }}</td>
                    <td>{{ $i->created_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $i->resolution_date ? \Carbon\Carbon::parse($i->resolution_date)->format('d M Y') : '—' }}</td>
                    <td class="text-end pe-3 fw-semibold {{ $days > 14 ? 'text-danger' : 'text-dark' }}">
                        {{ $days }}d
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        {{ __('projects.no_issues_found') ?: 'No quality defect records found matching criteria.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <div class="mt-4">
        {{ $issues->links() }}
    </div>
</div>
@endsection
