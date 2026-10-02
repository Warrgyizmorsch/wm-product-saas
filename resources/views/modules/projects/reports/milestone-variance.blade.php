@extends('layouts.duralux')

@section('title', (__('projects.report_variance_name') ?: 'Milestone Variance Report') . ' | SaaS ERP')
@section('page-title', __('projects.report_variance_name') ?: 'Milestone Variance Report')
@section('breadcrumb', (__('projects.reports') ?: 'Reports') . ' / ' . (__('projects.report_variance_name') ?: 'Milestone Variance'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.reports.milestone-variance') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.reports.milestone-variance')">
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
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') ?: 'Status' }}</label>
                    <x-ui.odoo-form-ui type="select" name="status">
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
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'milestone-variance', 'format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'milestone-variance', 'format' => 'csv'] + request()->query()) }}">
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
                <th class="ps-3">{{ __('projects.milestone_name') ?: 'Milestone' }}</th>
                <th>{{ __('projects.project') ?: 'Project' }}</th>
                <th>{{ __('projects.status') ?: 'Status' }}</th>
                <th>{{ __('projects.start_date') ?: 'Start' }}</th>
                <th>{{ __('projects.due_date') ?: 'Planned Due' }}</th>
                <th>{{ __('projects.actual_completion') ?: 'Actual Finish' }}</th>
                <th class="text-center">{{ __('projects.schedule_slippage') ?: 'Slippage' }}</th>
                <th class="text-end">{{ __('projects.planned_cost') ?: 'Planned Cost' }} ({{ active_currency_symbol() }})</th>
                <th class="text-center pe-3">{{ __('projects.completion_percent') ?: 'Progress' }}</th>
            </tr>
        </thead>
        <tbody class="fs-13">
            @forelse ($milestones as $m)
                @php
                    $slippage = 0;
                    if ($m->completed_at && $m->due_date) {
                        $slippage = max((int) \Carbon\Carbon::parse($m->due_date)->diffInDays(\Carbon\Carbon::parse($m->completed_at), false), 0);
                    } elseif (!$m->completed_at && $m->due_date && \Carbon\Carbon::today()->gt(\Carbon\Carbon::parse($m->due_date))) {
                        $slippage = (int) \Carbon\Carbon::parse($m->due_date)->diffInDays(\Carbon\Carbon::today());
                    }
                @endphp
                <tr>
                    <td class="ps-3 fw-bold text-dark">{{ $m->name }}</td>
                    <td class="text-muted">{{ $m->project?->name ?? '—' }}</td>
                    <td>
                        <x-ui.badge variant="{{ $m->status === 'Completed' ? 'success' : ($m->status === 'Active' ? 'primary' : 'secondary') }}" soft>
                            {{ ucfirst($m->status) }}
                        </x-ui.badge>
                    </td>
                    <td>{{ $m->start_date ? \Carbon\Carbon::parse($m->start_date)->format('d M Y') : '—' }}</td>
                    <td>{{ $m->due_date ? \Carbon\Carbon::parse($m->due_date)->format('d M Y') : '—' }}</td>
                    <td>{{ $m->completed_at ? \Carbon\Carbon::parse($m->completed_at)->format('d M Y') : '—' }}</td>
                    <td class="text-center">
                        @if ($slippage > 0)
                            <span class="badge bg-soft-danger text-danger fs-11 fw-semibold">+{{ $slippage }}d</span>
                        @else
                            <span class="badge bg-soft-success text-success fs-11">0d</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">{{ format_currency($m->planned_cost ?? 0) }}</td>
                    <td class="text-center pe-3">
                        <span class="badge bg-soft-info text-info">{{ $m->completion_percentage ?? 0 }}%</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        {{ __('projects.no_milestones_found') ?: 'No milestone records found matching criteria.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <div class="mt-4">
        {{ $milestones->links() }}
    </div>
</div>
@endsection
