@extends('layouts.duralux')

@section('title', (__('projects.report_utilization_name') ?: 'Resource Utilization & Productivity') . ' | SaaS ERP')
@section('page-title', __('projects.report_utilization_name') ?: 'Resource Utilization & Productivity')
@section('breadcrumb', (__('projects.reports') ?: 'Reports') . ' / ' . (__('projects.report_utilization_name') ?: 'Resource Utilization'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.reports.resource-utilization') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.reports.resource-utilization')">
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
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.team_member') ?: 'Team Member' }}</label>
                    <x-ui.odoo-form-ui type="select" name="user_id">
                        <option value="">{{ __('projects.all_members') ?: 'All Team Members' }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($filters->user_id == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.time_period') ?: 'Time Period' }}</label>
                    <x-ui.odoo-form-ui type="select" name="preset">
                        <option value="this_month" @selected($filters->preset === 'this_month')>{{ __('projects.this_month') ?: 'This Month' }}</option>
                        <option value="today" @selected($filters->preset === 'today')>{{ __('projects.today') ?: 'Today' }}</option>
                        <option value="this_week" @selected($filters->preset === 'this_week')>{{ __('projects.this_week') ?: 'This Week' }}</option>
                        <option value="last_month" @selected($filters->preset === 'last_month')>{{ __('projects.last_month') ?: 'Last Month' }}</option>
                        <option value="this_quarter" @selected($filters->preset === 'this_quarter')>{{ __('projects.this_quarter') ?: 'This Quarter' }}</option>
                        <option value="this_year" @selected($filters->preset === 'this_year')>{{ __('projects.this_year') ?: 'This Year' }}</option>
                        <option value="all_time" @selected($filters->preset === 'all_time')>{{ __('projects.all_time') ?: 'All Time' }}</option>
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
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'resource-utilization', 'format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'resource-utilization', 'format' => 'csv'] + request()->query()) }}">
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
                <th class="ps-3">{{ __('projects.team_member') ?: 'Team Member' }}</th>
                <th class="text-center">{{ __('projects.projects_count') ?: 'Assigned Projects' }}</th>
                <th class="text-center">{{ __('projects.open_tasks') ?: 'Open Tasks' }}</th>
                <th class="text-center">{{ __('projects.completed_tasks') ?: 'Completed Tasks' }}</th>
                <th class="text-end">{{ __('projects.budget_hours') ?: 'Budgeted (h)' }}</th>
                <th class="text-end">{{ __('projects.total_hours') ?: 'Total Logged (h)' }}</th>
                <th class="text-end">{{ __('projects.billable_hours') ?: 'Billable (h)' }}</th>
                <th class="text-center">{{ __('projects.billable_ratio') ?: 'Billable Ratio' }}</th>
                <th class="text-center pe-3">{{ __('projects.budget_burn') ?: 'Budget Burn' }}</th>
            </tr>
        </thead>
        <tbody class="fs-13">
            @forelse ($members as $m)
                <tr>
                    <td class="ps-3">
                        <span class="fw-semibold text-dark">{{ $m['user_name'] }}</span>
                        <span class="fs-11 text-muted d-block">{{ $m['user_email'] }}</span>
                    </td>
                    <td class="text-center">{{ $m['project_count'] }}</td>
                    <td class="text-center">
                        <span class="badge bg-soft-warning text-warning">{{ $m['open_tasks'] }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-soft-success text-success">{{ $m['completed_tasks'] }}</span>
                    </td>
                    <td class="text-end text-muted">{{ number_format($m['budget_hours'], 1) }}h</td>
                    <td class="text-end fw-semibold text-dark">{{ number_format($m['total_hours'], 1) }}h</td>
                    <td class="text-end text-primary">{{ number_format($m['billable_hours'], 1) }}h</td>
                    <td class="text-center">
                        <span class="badge bg-soft-info text-info fs-11">{{ $m['billable_percent'] }}%</span>
                    </td>
                    <td class="text-center pe-3">
                        @if ($m['burn_percent'] !== null)
                            <span class="badge {{ $m['burn_percent'] > 100 ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success' }} fs-11">
                                {{ $m['burn_percent'] }}%
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        {{ __('projects.no_members_found') ?: 'No team member activity found matching criteria.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <div class="mt-4">
        {{ $members->links() }}
    </div>
</div>
@endsection
