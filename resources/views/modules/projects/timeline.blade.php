@extends('layouts.duralux')

@section('title', __('projects.timeline') . ' | ' . $project->project_code . ' | SaaS ERP')
@section('page-title', __('projects.gantt_chart') . ' — ' . $project->name)
@section('breadcrumb', __('projects.title') . ' / ' . $project->project_code . ' / ' . __('projects.timeline'))

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('projects.show', $project) }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>{{ __('projects.back_to_project') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="feather-calendar text-primary"></i>
                    <span>{{ __('projects.gantt_chart') }}</span>
                    <span class="badge bg-soft-primary text-primary fs-12">{{ $project->project_code }}</span>
                </h4>
                <p class="text-muted fs-13 mb-0">{{ __('projects.interactive_gantt_planner', ['defaultValue' => 'Interactive project timeline, CPM critical path analysis, and dependency scheduling.']) }}</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('projects.show', [$project, 'tab' => 'milestones']) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="feather-flag me-1"></i>{{ __('projects.milestones') }}
                </a>
            </div>
        </div>

        @include('modules.projects._timeline', [
            'project'             => $project,
            'milestoneId'         => $selectedMilestoneId ?? null,
            'milestones'          => $milestones,
            'selectedMilestoneId' => $selectedMilestoneId ?? null,
        ])
    </div>
@endsection
