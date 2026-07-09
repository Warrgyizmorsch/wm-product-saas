@extends('layouts.duralux')

@section('title', $project->project_code . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', $project->name)
@section('breadcrumb', __('projects.title') . ' / ' . $project->project_code)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('projects.edit', $project->id) }}" class="btn btn-light">
            <i class="feather-edit-2 me-2"></i>{{ __('projects.edit') }}
        </a>
        <form action="{{ route('projects.destroy', $project->id) }}" method="POST"
              onsubmit="return confirm('{{ __('projects.confirm_delete') }}');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="feather-trash-2 me-2"></i>{{ __('projects.delete') }}
            </button>
        </form>
        <a href="{{ route('projects.index') }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>{{ __('projects.back') }}
        </a>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">{{ __('projects.success') }}</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="feather-briefcase me-2 text-primary"></i>{{ $project->project_code }}
                    </h5>
                    @if ($project->status === 'Active')
                        <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-semibold">{{ __('projects.statuses.Active') }}</span>
                    @elseif ($project->status === 'On Hold')
                        <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 fw-semibold">{{ __('projects.statuses.On Hold') }}</span>
                    @elseif ($project->status === 'Completed')
                        <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 fw-semibold">{{ __('projects.statuses.Completed') }}</span>
                    @elseif ($project->status === 'Closed')
                        <span class="badge bg-soft-dark text-dark px-2 py-1 fs-11 fw-semibold">{{ __('projects.statuses.Closed') }}</span>
                    @else
                        <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold">{{ __('projects.statuses.Draft') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-4 fs-13">
                        <div class="col-md-6">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.project_name') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->name }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.client') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->customer?->name ?: '—' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.project_owner') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->owner?->name ?: '—' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.project_manager') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->manager?->name ?: '—' }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.start_date') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->start_date?->format('d/m/Y') ?: '—' }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.end_date') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->end_date?->format('d/m/Y') ?: '—' }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.priority') }}</span>
                            <span class="fw-semibold text-dark">{{ __('projects.priorities.' . $project->priority) }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.billing_method') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->billing_method ? __('projects.billing_methods.' . $project->billing_method) : '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.budget_type') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->budget_type ? __('projects.budget_types.' . $project->budget_type) : '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.budget_amount') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->budget_amount !== null ? number_format((float) $project->budget_amount, 2) : '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.budget_hours') }}</span>
                            <span class="fw-semibold text-dark">{{ $project->budget_hours !== null ? number_format((float) $project->budget_hours, 2) : '—' }}</span>
                        </div>
                        @if ($project->description)
                            <div class="col-12">
                                <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('projects.description') }}</span>
                                <p class="mb-0 text-dark">{{ $project->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            @include('modules.projects._activity-feed', [
                'activities' => $activities,
                'viewAllUrl' => route('projects.activity', $project->id),
            ])
        </div>
    </div>
@endsection
