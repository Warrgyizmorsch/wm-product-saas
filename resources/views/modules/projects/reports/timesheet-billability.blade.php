@extends('layouts.duralux')

@section('title', (__('projects.report_timesheet_name') ?: 'Timesheet & Billability Report') . ' | SaaS ERP')
@section('page-title', __('projects.report_timesheet_name') ?: 'Timesheet & Billability Report')
@section('breadcrumb', (__('projects.reports') ?: 'Reports') . ' / ' . (__('projects.report_timesheet_name') ?: 'Timesheet & Billability'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.reports.timesheet-billability') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.reports.timesheet-billability')">
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
                        <option value="">{{ __('projects.all_members') ?: 'All Members' }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($filters->user_id == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.approval_status') ?: 'Approval Status' }}</label>
                    <x-ui.odoo-form-ui type="select" name="approval_status">
                        <option value="">{{ __('projects.all_statuses') ?: 'All Statuses' }}</option>
                        @foreach ($approvalStatuses as $as)
                            <option value="{{ $as }}" @selected($filters->approval_status === $as)>{{ ucfirst($as) }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.billability') ?: 'Billability' }}</label>
                    <x-ui.odoo-form-ui type="select" name="billable">
                        <option value="">{{ __('projects.all') ?: 'All (Billable & Non-billable)' }}</option>
                        <option value="1" @selected($filters->billable === true)>{{ __('projects.billable_only') ?: 'Billable Only' }}</option>
                        <option value="0" @selected($filters->billable === false)>{{ __('projects.non_billable_only') ?: 'Non-Billable Only' }}</option>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.invoiced_state') ?: 'Invoiced State' }}</label>
                    <x-ui.odoo-form-ui type="select" name="invoiced_status">
                        <option value="">{{ __('projects.all') ?: 'All Invoiced States' }}</option>
                        <option value="invoiced" @selected($filters->invoiced_status === 'invoiced')>{{ __('projects.invoiced') ?: 'Invoiced' }}</option>
                        <option value="unbilled" @selected($filters->invoiced_status === 'unbilled')>{{ __('projects.unbilled') ?: 'Unbilled' }}</option>
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
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'timesheet-billability', 'format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'timesheet-billability', 'format' => 'csv'] + request()->query()) }}">
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

    {{-- Report Table --}}
    <x-ui.odoo-form-ui type="table" hoverable responsive>
        <thead>
            <tr class="fs-11 text-uppercase text-muted border-bottom">
                <th class="ps-3">{{ __('projects.date') ?: 'Date' }}</th>
                <th>{{ __('projects.team_member') ?: 'Member' }}</th>
                <th>{{ __('projects.project') ?: 'Project' }}</th>
                <th>{{ __('projects.task') ?: 'Task' }}</th>
                <th class="text-end">{{ __('projects.hours') ?: 'Hours' }}</th>
                <th class="text-center">{{ __('projects.billable') ?: 'Billable' }}</th>
                <th class="text-end">{{ __('projects.hourly_rate') ?: 'Rate ($)' }}</th>
                <th class="text-end">{{ __('projects.billable_amount') ?: 'Amount ($)' }}</th>
                <th class="text-center">{{ __('projects.approval') ?: 'Approval' }}</th>
                <th class="text-center pe-3">{{ __('projects.invoice_status') ?: 'Invoiced' }}</th>
            </tr>
        </thead>
        <tbody class="fs-13">
            @forelse ($timeLogs as $tl)
                @php
                    $rate = (float) ($tl->hourly_rate ?? 0);
                    $amount = (float) $tl->hours * $rate;
                @endphp
                <tr>
                    <td class="ps-3 text-muted">{{ $tl->date ? \Carbon\Carbon::parse($tl->date)->format('d M Y') : '—' }}</td>
                    <td class="fw-semibold text-dark">{{ $tl->user?->name ?? '—' }}</td>
                    <td>
                        <span class="font-monospace text-primary fs-12">{{ $tl->project?->project_code }}</span>
                        <span class="text-muted d-block fs-11">{{ $tl->project?->name }}</span>
                    </td>
                    <td class="text-dark">{{ $tl->task?->title ?? '—' }}</td>
                    <td class="text-end fw-bold">{{ number_format((float) $tl->hours, 2) }}h</td>
                    <td class="text-center">
                        @if ($tl->is_billable)
                            <x-ui.badge variant="success" soft>{{ __('projects.yes') ?: 'Yes' }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" soft>{{ __('projects.no') ?: 'No' }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="text-end text-muted">${{ number_format($rate, 2) }}</td>
                    <td class="text-end fw-semibold">${{ number_format($amount, 2) }}</td>
                    <td class="text-center">
                        <x-ui.badge variant="{{ $tl->approval_status === 'Approved' ? 'success' : ($tl->approval_status === 'Rejected' ? 'danger' : 'warning') }}" soft>
                            {{ ucfirst($tl->approval_status) }}
                        </x-ui.badge>
                    </td>
                    <td class="text-center pe-3">
                        @if ($tl->is_invoiced)
                            <x-ui.badge variant="primary" soft>{{ __('projects.invoiced') ?: 'Invoiced' }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" soft>{{ __('projects.unbilled') ?: 'Unbilled' }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        {{ __('projects.no_timelogs_found') ?: 'No work log entries found matching criteria.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <div class="mt-4">
        {{ $timeLogs->links() }}
    </div>
</div>
@endsection
