@extends('layouts.duralux')

@section('title', __('projects.title') . ' | SaaS ERP')
@section('page-title', __('projects.title'))
@section('breadcrumb', __('projects.title'))

@push('styles')
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }
    </style>
@endpush

@section('page-actions')
    @can('create', \App\Domains\Projects\Models\Project::class)
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
            <i class="feather-plus me-2"></i>{{ __('projects.new_project') }}
        </button>
    @endcan
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'project_code');
        $sortOrder = request('sort_order', 'asc');

        $sortedProjects = match ($sortBy) {
            'name' => $sortOrder === 'desc' ? $projects->sortByDesc('name') : $projects->sortBy('name'),
            'start_date' => $sortOrder === 'desc' ? $projects->sortByDesc('start_date') : $projects->sortBy('start_date'),
            default => $sortOrder === 'desc' ? $projects->sortByDesc('project_code') : $projects->sortBy('project_code'),
        };

        $currentPage = (int) request('page', 1);
        $perPage = 10;
        $totalResults = $sortedProjects->count();
        $totalPages = (int) ceil($totalResults / $perPage);
        $paginatedProjects = $sortedProjects->slice(($currentPage - 1) * $perPage, $perPage);
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <!-- Metrics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full border-0 shadow-sm">
                    <div class="card-body">
                        <span class="text-muted fs-12 text-uppercase">{{ __('projects.total_projects') }}</span>
                        <h3 class="mb-0 mt-2 fw-bold text-dark">{{ $summary['total'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full border-0 shadow-sm">
                    <div class="card-body">
                        <span class="text-muted fs-12 text-uppercase">{{ __('projects.active_projects') }}</span>
                        <h3 class="mb-0 mt-2 fw-bold text-dark">{{ $summary['active'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-briefcase me-2 text-primary"></i>{{ __('projects.project_directory') }}
            </h5>
            <div class="d-flex gap-2 ms-auto">
                <x-ui.sort-dropdown :label="__('projects.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'project_code', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'project_code' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('projects.sort_code_asc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'project_code', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'project_code' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('projects.sort_code_desc') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('projects.sort_name_asc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('projects.sort_name_desc') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'start_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'start_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('projects.sort_start_date_asc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'start_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'start_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('projects.sort_start_date_desc') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <form method="GET" action="{{ route('projects.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('projects.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('projects.search_placeholder') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('projects.all_statuses') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('projects.statuses.' . $status) }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('projects.index') }}" class="btn btn-sm btn-light border">{{ __('projects.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 12%">{{ __('projects.code') }}</th>
                        <th style="width: 20%">{{ __('projects.name') }}</th>
                        <th style="width: 15%">{{ __('projects.client') }}</th>
                        <th style="width: 13%">{{ __('projects.owner') }}</th>
                        <th style="width: 10%">{{ __('projects.priority') }}</th>
                        <th style="width: 10%">{{ __('projects.status') }}</th>
                        <th style="width: 8%">{{ __('projects.start_date') }}</th>
                        <th style="width: 9%">{{ __('projects.end_date') }}</th>
                        <th class="text-end" style="width: 10%">{{ __('projects.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($paginatedProjects as $project)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <a href="{{ route('projects.show', $project->id) }}" class="fw-bold text-primary hover-primary">
                                    {{ $project->project_code }}
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $project->name }}</span>
                            </td>
                            <td>{{ $project->customer?->name ?: '—' }}</td>
                            <td>{{ $project->owner?->name ?: '—' }}</td>
                            <td>
                                <span class="badge {{ in_array($project->priority, ['High', 'Critical']) ? 'bg-soft-danger text-danger' : 'bg-soft-secondary text-secondary' }} px-2 py-0.5 fs-11 fw-semibold">
                                    {{ __('projects.priorities.' . $project->priority) }}
                                </span>
                            </td>
                            <td>
                                @if ($project->status === 'Active')
                                    <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11 fw-semibold">{{ __('projects.statuses.Active') }}</span>
                                @elseif ($project->status === 'On Hold')
                                    <span class="badge bg-soft-warning text-warning px-2 py-0.5 fs-11 fw-semibold">{{ __('projects.statuses.On Hold') }}</span>
                                @elseif ($project->status === 'Completed')
                                    <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11 fw-semibold">{{ __('projects.statuses.Completed') }}</span>
                                @elseif ($project->status === 'Closed')
                                    <span class="badge bg-soft-dark text-dark px-2 py-0.5 fs-11 fw-semibold">{{ __('projects.statuses.Closed') }}</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-11 fw-semibold">{{ __('projects.statuses.Draft') }}</span>
                                @endif
                            </td>
                            <td>{{ $project->start_date?->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $project->end_date?->format('d/m/Y') ?: '—' }}</td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('projects.show', $project->id)">
                                    <li>
                                        <a href="{{ route('projects.edit', $project->id) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('projects.edit') }}
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('projects.destroy', $project->id) }}" onsubmit="return confirm('{{ __('projects.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('projects.delete') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>{{ __('projects.no_projects') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <x-ui.pagination :currentPage="$currentPage" :totalPages="$totalPages" :totalResults="$totalResults" :perPage="$perPage" />
    </div>

    @can('create', \App\Domains\Projects\Models\Project::class)
        <x-ui.modal id="createProjectModal" title="{{ __('projects.new_project') }}" size="lg" :scrollable="true" :static="true" :showFooter="false">
            <form action="{{ route('projects.store') }}" method="POST">
                @csrf

                <p class="text-muted fs-12 mb-3">{{ __('projects.code_auto_generated', ['code' => $nextCode]) }}</p>

                @include('modules.projects._form-fields', [
                    'project' => null,
                    'statusOptions' => [
                        \App\Domains\Projects\Models\Project::STATUS_DRAFT,
                        \App\Domains\Projects\Models\Project::STATUS_ACTIVE,
                        \App\Domains\Projects\Models\Project::STATUS_ON_HOLD,
                        \App\Domains\Projects\Models\Project::STATUS_COMPLETED,
                    ],
                ])

                <div class="d-flex gap-2 justify-content-end pt-3 border-top mt-4">
                    <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="feather-check-circle me-2"></i>{{ __('projects.save_project') }}
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
@endsection
