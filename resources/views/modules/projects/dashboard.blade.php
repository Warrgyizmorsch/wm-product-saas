@extends('layouts.duralux')

@section('title', (__('projects.executive_dashboard') ?: 'Executive Dashboard') . ' | SaaS ERP')
@section('page-title', __('projects.executive_dashboard') ?: 'Executive Dashboard')
@section('breadcrumb', (__('ui.projects') ?: 'Projects') . ' / ' . (__('projects.executive_dashboard') ?: 'Executive Dashboard'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- Custom Filter Component (same as BOM module) --}}
        <form id="dashboard-filter-form" method="GET" action="{{ route('projects.dashboard') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.dashboard')">
                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('projects.filter_options') ?: 'Filter Options' }}</h6>

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
                        <option value="custom" @selected($filters->preset === 'custom')>{{ __('projects.custom_range') ?: 'Custom Range' }}</option>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.start_date') ?: 'Start Date' }} / {{ __('projects.end_date') ?: 'End Date' }}</label>
                    <div class="d-flex gap-2">
                        <x-ui.odoo-form-ui type="input" inputType="date" name="start_date" value="{{ $filters->start_date?->toDateString() }}" />
                        <x-ui.odoo-form-ui type="input" inputType="date" name="end_date" value="{{ $filters->end_date?->toDateString() }}" />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.project') ?: 'Project' }}</label>
                    <x-ui.odoo-form-ui type="select" name="project_id">
                        <option value="">{{ __('projects.all_projects') ?: 'All Projects' }}</option>
                        @foreach ($projects as $proj)
                            <option value="{{ $proj->id }}" @selected($filters->project_id == $proj->id)>{{ $proj->project_code }} - {{ $proj->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
            </x-ui.filter>
        </form>

        {{-- Export Dropdown --}}
        <div class="dropdown">
            <x-ui.button variant="light-brand" class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" icon="feather-download">
                {{ __('projects.export') ?: 'Export' }}
            </x-ui.button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item" href="{{ route('projects.dashboard.export', ['format' => 'csv'] + request()->query()) }}">
                        <i class="feather-file-text me-2 text-primary"></i>{{ __('projects.export_csv') ?: 'Export CSV' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('projects.dashboard.export', ['format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Export Excel (.xlsx)' }}
                    </a>
                </li>
            </ul>
        </div>

        {{-- Navigation Shortcuts --}}
        <x-ui.button href="{{ route('projects.reports.index') }}" variant="soft-primary" icon="feather-bar-chart-2">
            {{ __('projects.reports') ?: 'Reports' }}
        </x-ui.button>
        <x-ui.button href="{{ route('projects.index') }}" variant="primary" icon="feather-folder">
            {{ __('projects.projects_directory') ?: 'Projects Directory' }}
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border">
    {{-- Top Executive KPI Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Active Projects & Health --}}
        <div class="col-xl-3 col-md-6">
            <x-ui.card class="h-100 border shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">{{ __('projects.active_projects') ?: 'Active Projects' }}</span>
                    <span class="badge bg-soft-primary text-primary fs-11 fw-bold">{{ $kpis->total_projects }} {{ __('projects.total') ?: 'Total' }}</span>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <span class="fs-24 fw-bold text-dark">{{ $kpis->active_projects }}</span>
                    <span class="fs-12 text-muted">/ {{ $kpis->completed_projects }} {{ __('projects.status_completed') ?: 'Completed' }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-between fs-11">
                    <span class="text-muted">{{ __('projects.portfolio_health') ?: 'Portfolio Health' }}:</span>
                    <span class="fw-bold {{ $kpis->portfolio_health_score >= 80 ? 'text-success' : ($kpis->portfolio_health_score >= 60 ? 'text-warning' : 'text-danger') }}">
                        {{ $kpis->portfolio_health_score }}% {{ __('projects.on_track') ?: 'On Track' }}
                    </span>
                </div>
            </x-ui.card>
        </div>

        {{-- Budget Consumed vs Planned --}}
        <div class="col-xl-3 col-md-6">
            <x-ui.card class="h-100 border shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">{{ __('projects.budget_consumed') ?: 'Budget Consumed' }}</span>
                    <span class="badge bg-soft-success text-success fs-11 fw-bold">{{ $kpis->cost_consumption_percent ?? 0 }}%</span>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <span class="fs-24 fw-bold text-dark">{{ format_currency($kpis->total_incurred_cost) }}</span>
                    <span class="fs-12 text-muted">/ {{ format_currency($kpis->total_budget_amount) }}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar {{ ($kpis->cost_consumption_percent ?? 0) > 100 ? 'bg-danger' : (($kpis->cost_consumption_percent ?? 0) > 85 ? 'bg-warning' : 'bg-success') }}"
                         role="progressbar"
                         style="width: {{ min($kpis->cost_consumption_percent ?? 0, 100) }}%"></div>
                </div>
            </x-ui.card>
        </div>

        {{-- Hours Tracked vs Budget --}}
        <div class="col-xl-3 col-md-6">
            <x-ui.card class="h-100 border shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">{{ __('projects.hours_tracked') ?: 'Hours Tracked' }}</span>
                    <span class="badge bg-soft-info text-info fs-11 fw-bold">{{ $kpis->hours_consumption_percent ?? 0 }}%</span>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <span class="fs-24 fw-bold text-dark">{{ number_format($kpis->total_tracked_hours, 1) }}h</span>
                    <span class="fs-12 text-muted">/ {{ number_format($kpis->total_budget_hours, 1) }}h</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar {{ ($kpis->hours_consumption_percent ?? 0) > 100 ? 'bg-danger' : (($kpis->hours_consumption_percent ?? 0) > 85 ? 'bg-warning' : 'bg-info') }}"
                         role="progressbar"
                         style="width: {{ min($kpis->hours_consumption_percent ?? 0, 100) }}%"></div>
                </div>
            </x-ui.card>
        </div>

        {{-- Overdue Deliverables & Open Defects --}}
        <div class="col-xl-3 col-md-6">
            <x-ui.card class="h-100 border shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-12 fw-semibold text-uppercase">{{ __('projects.overdue_and_defects') ?: 'Overdue & Defects' }}</span>
                    <span class="badge {{ $kpis->critical_issues_count > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-secondary text-secondary' }} fs-11 fw-bold">
                        {{ $kpis->critical_issues_count }} {{ __('projects.critical') ?: 'Critical' }}
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-3 mb-2">
                    <div>
                        <span class="fs-24 fw-bold text-danger">{{ $kpis->overdue_tasks_count }}</span>
                        <span class="fs-11 text-muted d-block">{{ __('projects.overdue_tasks') ?: 'Tasks Overdue' }}</span>
                    </div>
                    <div class="border-start ps-3">
                        <span class="fs-24 fw-bold text-warning">{{ $kpis->open_issues_count }}</span>
                        <span class="fs-11 text-muted d-block">{{ __('projects.open_issues') ?: 'Open Issues' }}</span>
                    </div>
                </div>
                <div class="fs-11 text-muted">
                    {{ __('projects.unbilled_approved') ?: 'Unbilled Approved' }}: <strong class="text-dark">{{ number_format($kpis->unbilled_approved_hours, 1) }}h ({{ format_currency($kpis->unbilled_amount) }})</strong>
                </div>
            </x-ui.card>
        </div>
    </div>

    {{-- Main Analytics Horizon --}}
    <div class="row g-4">
        {{-- Left Column: Project Health & Workload --}}
        <div class="col-xl-8">
            {{-- Active Projects Health Table --}}
            <x-ui.card :title="__('projects.active_projects_health') ?: 'Active Projects & Health'" class="mb-4 border shadow-sm">
                <x-ui.odoo-form-ui type="table" hoverable responsive>
                    <thead>
                        <tr class="fs-11 text-uppercase text-muted border-bottom">
                            <th class="ps-3">{{ __('projects.project') ?: 'Project' }}</th>
                            <th>{{ __('projects.client') ?: 'Client' }}</th>
                            <th>{{ __('projects.health') ?: 'Health' }}</th>
                            <th class="text-center">{{ __('projects.progress') ?: 'Progress' }}</th>
                            <th class="text-end">{{ __('projects.tracked_vs_budget') ?: 'Tracked / Budget' }}</th>
                            <th class="text-center pe-3">{{ __('projects.actions') ?: 'Action' }}</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13">
                        @forelse ($healthList as $proj)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('projects.show', $proj['id']) }}" class="fw-semibold text-primary text-decoration-none">
                                        {{ $proj['project_code'] }} - {{ $proj['name'] }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $proj['client_name'] }}</td>
                                <td>
                                    @if ($proj['health_state'] === 'critical')
                                        <x-ui.badge variant="danger" soft>{{ __('projects.health_critical') ?: 'Critical' }}</x-ui.badge>
                                    @elseif ($proj['health_state'] === 'at_risk')
                                        <x-ui.badge variant="warning" soft>{{ __('projects.health_at_risk') ?: 'At Risk' }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="success" soft>{{ __('projects.health_on_track') ?: 'On Track' }}</x-ui.badge>
                                    @endif
                                    @if ($proj['health_reason'])
                                        <div class="fs-10 text-muted">{{ $proj['health_reason'] }}</div>
                                    @endif
                                </td>
                                <td class="text-center" style="width: 140px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 5px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $proj['progress'] }}%"></div>
                                        </div>
                                        <span class="fs-11 text-muted">{{ $proj['progress'] }}%</span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="fw-semibold">{{ number_format($proj['actual_hours'], 1) }}h</span>
                                    <span class="text-muted fs-11">/ {{ number_format($proj['budget_hours'], 1) }}h</span>
                                </td>
                                <td class="text-center pe-3">
                                    <a href="{{ route('projects.show', $proj['id']) }}" class="btn btn-sm btn-icon btn-light" title="{{ __('projects.view') ?: 'View' }}">
                                        <i class="feather-arrow-right fs-12"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    {{ __('projects.no_active_projects') ?: 'No active projects found.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </x-ui.card>

            {{-- Team Member Workload Summary --}}
            <x-ui.card :title="__('projects.team_workload_distribution') ?: 'Team Workload Distribution'" class="border shadow-sm">
                <x-ui.odoo-form-ui type="table" hoverable responsive>
                    <thead>
                        <tr class="fs-11 text-uppercase text-muted border-bottom">
                            <th class="ps-3">{{ __('projects.team_member') ?: 'Team Member' }}</th>
                            <th class="text-center">{{ __('projects.projects_count') ?: 'Projects' }}</th>
                            <th class="text-center">{{ __('projects.open_tasks') ?: 'Open Tasks' }}</th>
                            <th class="text-end">{{ __('projects.logged_hours') ?: 'Logged Hours' }}</th>
                            <th class="text-end pe-3">{{ __('projects.budget_hours') ?: 'Budgeted Hours' }}</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13">
                        @forelse ($workload as $w)
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-semibold text-dark">{{ $w['user_name'] }}</span>
                                    <span class="fs-11 text-muted d-block">{{ $w['user_email'] }}</span>
                                </td>
                                <td class="text-center">{{ $w['project_count'] }}</td>
                                <td class="text-center">
                                    <span class="badge bg-soft-primary text-primary">{{ $w['open_tasks'] }}</span>
                                </td>
                                <td class="text-end fw-semibold">{{ number_format($w['logged_hours'], 1) }}h</td>
                                <td class="text-end pe-3 text-muted">
                                    {{ $w['budget_hours'] > 0 ? number_format($w['budget_hours'], 1) . 'h' : '—' }}
                                    @if ($w['burn_percent'] !== null)
                                        <span class="badge {{ $w['burn_percent'] > 100 ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success' }} fs-10 ms-1">
                                            {{ $w['burn_percent'] }}%
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    {{ __('projects.no_members_found') ?: 'No project collaborator data found.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </x-ui.card>
        </div>

        {{-- Right Column: Defect Severity & Upcoming Milestones --}}
        <div class="col-xl-4">
            {{-- Defect Severity Distribution Card --}}
            <x-ui.card :title="__('projects.defects_distribution') ?: 'Defects by Severity'" class="mb-4 border shadow-sm">
                <div class="row g-2 text-center mb-3">
                    <div class="col-6">
                        <div class="p-2 rounded bg-soft-danger border border-danger-subtle">
                            <div class="fs-20 fw-bold text-danger">{{ $defectDist['critical'] }}</div>
                            <div class="fs-11 text-muted text-uppercase fw-semibold">{{ __('projects.critical') ?: 'Critical' }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded bg-soft-warning border border-warning-subtle">
                            <div class="fs-20 fw-bold text-warning">{{ $defectDist['high'] }}</div>
                            <div class="fs-11 text-muted text-uppercase fw-semibold">{{ __('projects.high') ?: 'High' }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded bg-soft-info border border-info-subtle">
                            <div class="fs-20 fw-bold text-info">{{ $defectDist['medium'] }}</div>
                            <div class="fs-11 text-muted text-uppercase fw-semibold">{{ __('projects.medium') ?: 'Medium' }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded bg-soft-secondary border border-secondary-subtle">
                            <div class="fs-20 fw-bold text-secondary">{{ $defectDist['low'] }}</div>
                            <div class="fs-11 text-muted text-uppercase fw-semibold">{{ __('projects.low') ?: 'Low' }}</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between fs-12 pt-2 border-top">
                    <span class="text-muted">{{ __('projects.resolution_ratio') ?: 'Resolution Ratio' }}:</span>
                    <span class="fw-bold text-success">{{ $defectDist['resolved'] }} / {{ $defectDist['total'] }} {{ __('projects.resolved') ?: 'Resolved' }}</span>
                </div>
            </x-ui.card>

            {{-- Upcoming Deliverables & Milestones Card --}}
            <x-ui.card :title="__('projects.upcoming_milestones') ?: 'Upcoming Milestones'" class="border shadow-sm">
                @if ($upcomingMilestones->isEmpty())
                    <div class="text-center py-4 text-muted fs-12">
                        {{ __('projects.no_upcoming_milestones') ?: 'No pending milestones due.' }}
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($upcomingMilestones as $m)
                            <div class="list-group-item px-0 py-2 border-bottom">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fw-semibold text-dark fs-13 text-truncate" style="max-width: 180px;">{{ $m->name }}</span>
                                    <span class="badge bg-soft-primary text-primary fs-10">{{ $m->due_date?->format('d M Y') }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between fs-11 text-muted">
                                    <span>{{ $m->project?->name }}</span>
                                    <span>{{ $m->completion_percentage ?? 0 }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
