@extends('layouts.duralux')

@section('title', (__('projects.report_budget_name') ?: 'Budget vs. Actual Cost Report') . ' | SaaS ERP')
@section('page-title', __('projects.report_budget_name') ?: 'Budget vs. Actual Cost Report')
@section('breadcrumb', (__('projects.reports') ?: 'Reports') . ' / ' . (__('projects.report_budget_name') ?: 'Budget vs Actual'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('projects.reports.budget-cost') }}" class="d-inline">
            <x-ui.filter :label="__('ui.filter') ?: 'Filter'" offset="0, 5" :resetUrl="route('projects.reports.budget-cost')">
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
                    <x-ui.odoo-form-ui type="select" name="customer_id" label="{{ __('projects.client') ?: 'Client' }}">
                        <option value="">{{ __('projects.all_clients') ?: 'All Clients' }}</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected($filters->customer_id == $c->id)>{{ $c->name }}</option>
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
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'budget-cost', 'format' => 'xlsx'] + request()->query()) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => 'budget-cost', 'format' => 'csv'] + request()->query()) }}">
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
                <th>{{ __('projects.project_name') ?: 'Project' }}</th>
                <th>{{ __('projects.client') ?: 'Client' }}</th>
                <th class="text-end">{{ __('projects.base_budget') ?: 'Base Budget' }}</th>
                <th class="text-end">{{ __('projects.cr_budget') ?: 'Approved CRs' }}</th>
                <th class="text-end">{{ __('projects.revised_budget') ?: 'Revised Budget' }}</th>
                <th class="text-end">{{ __('projects.actual_cost') ?: 'Actual Cost' }}</th>
                <th class="text-end">{{ __('projects.cost_variance') ?: 'Cost Variance' }}</th>
                <th class="text-end">{{ __('projects.revised_hours') ?: 'Rev. Hours' }}</th>
                <th class="text-end pe-3">{{ __('projects.actual_hours') ?: 'Act. Hours' }}</th>
            </tr>
        </thead>
        <tbody class="fs-13">
            @forelse ($rows as $r)
                @php
                    $isOverCost = $r['cost_variance'] < 0;
                    $isOverHours = $r['hours_variance'] < 0;
                @endphp
                <tr>
                    <td class="ps-3 fw-bold font-monospace">
                        <a href="{{ route('projects.show', $r['id']) }}" class="text-primary text-decoration-none">
                            {{ $r['project_code'] }}
                        </a>
                    </td>
                    <td class="fw-semibold text-dark">{{ $r['name'] }}</td>
                    <td class="text-muted">{{ $r['client_name'] }}</td>
                    <td class="text-end text-muted">${{ number_format($r['base_budget_amount'], 2) }}</td>
                    <td class="text-end text-info">${{ number_format($r['cr_budget_amount'], 2) }}</td>
                    <td class="text-end fw-semibold text-dark">${{ number_format($r['revised_budget_amount'], 2) }}</td>
                    <td class="text-end fw-bold text-dark">${{ number_format($r['actual_cost'], 2) }}</td>
                    <td class="text-end fw-bold {{ $isOverCost ? 'text-danger' : 'text-success' }}">
                        ${{ number_format($r['cost_variance'], 2) }}
                        @if ($r['cost_burn_percent'] !== null)
                            <span class="fs-11 fw-normal text-muted d-block">({{ $r['cost_burn_percent'] }}%)</span>
                        @endif
                    </td>
                    <td class="text-end text-muted">{{ number_format($r['revised_budget_hours'], 1) }}h</td>
                    <td class="text-end pe-3 fw-semibold {{ $isOverHours ? 'text-danger' : 'text-dark' }}">
                        {{ number_format($r['actual_hours'], 1) }}h
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        {{ __('projects.no_projects_found') ?: 'No project financial records found matching criteria.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>
</div>
@endsection
