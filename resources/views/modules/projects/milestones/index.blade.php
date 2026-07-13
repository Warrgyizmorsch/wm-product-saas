@extends('layouts.duralux')

@section('title', __('projects.milestones') . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', __('projects.milestones'))
@section('breadcrumb', __('projects.title') . ' / ' . __('projects.milestones'))

@section('content')
    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-flag me-2 text-primary"></i>{{ __('projects.milestones') }}
            </h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('projects.milestones.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('projects.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('projects.milestone_search_placeholder') }}" value="{{ $filters['search'] ?? '' }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('projects.all_statuses') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                        {{ __('projects.statuses.' . $status) ?? $status }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.project') }}</label>
                            <x-ui.odoo-form-ui type="select" name="project_id" select2Selector="default">
                                <option value="">{{ __('projects.all_projects') }}</option>
                                @foreach ($projects as $proj)
                                    <option value="{{ $proj->id }}" @selected(($filters['project_id'] ?? '') == $proj->id)>
                                        [{{ $proj->project_code }}] {{ $proj->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('projects.milestones.index') }}" class="btn btn-sm btn-light border">{{ __('projects.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('projects.milestone_name') }}</th>
                            <th scope="col">{{ __('projects.project') }}</th>
                            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.milestone_owner') }}</th>
                            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.start_date') }}</th>
                            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.due_date') }}</th>
                            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.status') }}</th>
                            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.completion_percentage') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($milestones as $milestone)
                            <tr @can('update', $milestone) role="button" style="cursor: pointer;"
                                    onclick="openMilestoneDetailsDrawer({
                                        id: {{ $milestone->id }},
                                        updateUrl: @js(route('projects.milestones.update', [$milestone->project, $milestone->id])),
                                        deleteUrl: @js(route('projects.milestones.destroy', [$milestone->project, $milestone->id])),
                                        name: @js($milestone->name),
                                        description: @js($milestone->description),
                                        ownerId: @js($milestone->owner_id),
                                        ownerName: @js($milestone->owner?->name),
                                        startDate: @js($milestone->start_date?->format('Y-m-d')),
                                        dueDate: @js($milestone->due_date?->format('Y-m-d')),
                                        startDateDisplay: @js($milestone->start_date?->format('d/m/Y')),
                                        dueDateDisplay: @js($milestone->due_date?->format('d/m/Y')),
                                        status: @js($milestone->status),
                                        completionPercentage: {{ $milestone->completion_percentage }}
                                    })"
                                @endcan>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $milestone->name }}</div>
                                    @if ($milestone->description)
                                        <div class="fs-11 text-muted">{{ \Illuminate\Support\Str::limit($milestone->description, 60) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('projects.show', $milestone->project) }}" class="fw-semibold text-primary hover-primary" onclick="event.stopPropagation();">
                                        {{ $milestone->project?->name ?: '—' }}
                                    </a>
                                    @if ($milestone->project)
                                        <div class="fs-11 text-muted">{{ $milestone->project->project_code }}</div>
                                    @endif
                                </td>
                                <td>{{ $milestone->owner?->name ?: '—' }}</td>
                                <td>{{ $milestone->start_date?->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $milestone->due_date?->format('d/m/Y') ?: '—' }}</td>
                                <td>
                                    @if ($milestone->status)
                                        @php
                                            $milestoneStatusVariant = match ($milestone->status) {
                                                'Active' => 'success',
                                                'On Hold' => 'warning',
                                                'Completed' => 'primary',
                                                'Closed' => 'dark',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <x-ui.badge variant="{{ $milestoneStatusVariant }}" soft>
                                            {{ __('projects.statuses.' . $milestone->status) }}
                                        </x-ui.badge>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $milestone->completion_percentage }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="feather-info me-2 fs-16"></i>{{ __('projects.no_milestones_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>

                <x-ui.pagination
                    :currentPage="$milestones->currentPage()"
                    :totalPages="$milestones->lastPage()"
                    :totalResults="$milestones->total()"
                    :perPage="$milestones->perPage()" />
            </div>
        </div>
    </div>

    @include('modules.projects.milestones._modal')
    @include('modules.projects.milestones._drawer')
@endsection
