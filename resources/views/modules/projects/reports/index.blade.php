@extends('layouts.duralux')

@section('title', (__('projects.reports') ?: 'Reports') . ' | SaaS ERP')
@section('page-title', __('projects.reports') ?: 'Project Reports')
@section('breadcrumb', (__('ui.projects') ?: 'Projects') . ' / ' . (__('projects.reports') ?: 'Reports'))

@section('page-actions')
    <x-ui.button href="{{ route('projects.dashboard') }}" variant="primary" icon="feather-grid">
        {{ __('projects.executive_dashboard') ?: 'Executive Dashboard' }}
    </x-ui.button>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border">
    <div class="mb-4">
        <h5 class="fw-bold text-dark mb-1">{{ __('projects.reports_directory') ?: 'Operational Reports Directory' }}</h5>
        <p class="text-muted fs-13 mb-0">{{ __('projects.reports_directory_desc') ?: 'Access detailed project portfolio intelligence, task execution horizons, team productivity, billability, defect density, and variance analytics.' }}</p>
    </div>

    <div class="row g-4">
        @foreach ($reports as $r)
            <div class="col-xl-4 col-md-6">
                <x-ui.card class="h-100 border shadow-sm hover-shadow transition-all">
                    <div class="d-flex align-items-start gap-3">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 flex-shrink-0">
                            <i class="{{ $r['icon'] }} fs-20"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">
                                <a href="{{ route($r['route']) }}" class="text-dark text-decoration-none">
                                    {{ $r['name'] }}
                                </a>
                            </h6>
                            <p class="text-muted fs-12 mb-3 lh-sm" style="min-height: 38px;">
                                {{ $r['description'] }}
                            </p>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <a href="{{ route($r['route']) }}" class="btn btn-sm btn-light border text-primary fw-semibold">
                                    {{ __('projects.view_report') ?: 'View Report' }} <i class="feather-arrow-right ms-1 fs-12"></i>
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-icon btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('projects.quick_export') ?: 'Quick Export' }}">
                                        <i class="feather-download fs-13"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => $r['id'], 'format' => 'xlsx']) }}">
                                                <i class="feather-grid me-2 text-success"></i>{{ __('projects.export_excel') ?: 'Excel (.xlsx)' }}
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item fs-12" href="{{ route('projects.reports.export', ['report' => $r['id'], 'format' => 'csv']) }}">
                                                <i class="feather-file-text me-2 text-primary"></i>{{ __('projects.export_csv') ?: 'CSV (.csv)' }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        @endforeach
    </div>
</div>
@endsection
