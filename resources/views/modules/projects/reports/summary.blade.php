@extends('layouts.duralux')

@section('title', (__('projects.report_summary_name') ?: 'Project Summary Report') . ' | SaaS ERP')
@section('page-title', __('projects.report_summary_name') ?: 'Project Summary Report')
@section('breadcrumb', (__('projects.reports') ?: 'Reports') . ' / ' . (__('projects.report_summary_name') ?: 'Project Summary'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.reports.summary') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.reports.summary')">
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
                        <option value="custom" @selected($filters->preset === 'custom')>{{ __('projects.custom_range') ?: 'Custom' }}</option>
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

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.client') ?: 'Client' }}</label>
                    <x-ui.odoo-form-ui type="select" name="customer_id">
                        <option value="">{{ __('projects.all_clients') ?: 'All Clients' }}</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected($filters->customer_id == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.search') ?: 'Search' }}</label>
                    <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('projects.search_placeholder') ?: 'Code or name...' }}" value="{{ $filters->search }}" />
                </div>
            </x-ui.filter>
        </form>

        <div class="dropdown">
            <x-ui.button variant="light-brand" class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" icon="feather-download">
                {{ __('projects.export') ?: 'Export' }}
            </x-ui.button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'summary', 'format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'summary', 'format' => 'csv'] + request()->query()) }}">
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
                <th class="ps-3">{{ __('projects.code') ?: 'Code' }}</th>
                <th>{{ __('projects.project_name') ?: 'Project Name' }}</th>
                <th>{{ __('projects.client') ?: 'Client' }}</th>
                <th>{{ __('projects.owner') ?: 'Owner' }}</th>
                <th>{{ __('projects.status') ?: 'Status' }}</th>
                <th>{{ __('projects.priority') ?: 'Priority' }}</th>
                <th>{{ __('projects.start_date') ?: 'Start' }}</th>
                <th>{{ __('projects.end_date') ?: 'End' }}</th>
                <th class="text-end">{{ __('projects.budget_amount') ?: 'Budget ($)' }}</th>
                <th class="text-end">{{ __('projects.budget_hours') ?: 'Budget (h)' }}</th>
                <th class="text-center pe-3">{{ __('projects.progress') ?: 'Progress' }}</th>
            </tr>
        </thead>
        <tbody class="fs-13">
            @forelse ($projects as $p)
                @php
                    $eligibleTasks = $p->tasks->where('status', '!=', 'Cancelled');
                    $doneTasks = $eligibleTasks->where('status', 'Completed');
                    $progress = $eligibleTasks->count() > 0 ? (int) round(($doneTasks->count() / $eligibleTasks->count()) * 100) : 0;
                @endphp
                <tr>
                    <td class="ps-3 fw-bold font-monospace">
                        <a href="{{ route('projects.show', $p->id) }}" class="text-primary text-decoration-none">
                            {{ $p->project_code }}
                        </a>
                    </td>
                    <td class="fw-semibold text-dark">{{ $p->name }}</td>
                    <td class="text-muted">{{ $p->customer?->name ?? '—' }}</td>
                    <td class="text-muted">{{ $p->owner?->name ?? '—' }}</td>
                    <td>
                        <x-ui.badge variant="secondary" soft>{{ ucfirst($p->status) }}</x-ui.badge>
                    </td>
                    <td>
                        <x-ui.badge variant="{{ $p->priority === 'Urgent' ? 'danger' : ($p->priority === 'High' ? 'warning' : 'info') }}" soft>
                            {{ ucfirst($p->priority) }}
                        </x-ui.badge>
                    </td>
                    <td>{{ $p->start_date?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $p->end_date?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end fw-semibold">${{ number_format((float) ($p->budget_amount ?? 0), 2) }}</td>
                    <td class="text-end">{{ number_format((float) ($p->budget_hours ?? 0), 1) }}h</td>
                    <td class="text-center pe-3">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <div class="progress" style="width: 60px; height: 5px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progress }}%"></div>
                            </div>
                            <span class="fs-11 text-muted">{{ $progress }}%</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        {{ __('projects.no_projects_found') ?: 'No project records found matching current criteria.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <div class="mt-4">
        {{ $projects->links() }}
    </div>
</div>
@endsection
