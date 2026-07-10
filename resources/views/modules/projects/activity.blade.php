@extends('layouts.duralux')

@section('title', __('projects.activity') . ' — ' . $project->project_code . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', __('projects.project_activity'))
@section('breadcrumb', __('projects.title') . ' / ' . $project->project_code . ' / ' . __('projects.activity'))

@section('page-actions')
    <a href="{{ route('projects.show', $project->id) }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>{{ __('projects.back_to_project') }}
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-8 mx-auto">
            @include('modules.projects._activity-feed', ['activities' => $activities])
        </div>
    </div>
@endsection
