@extends('layouts.duralux')

@section('title', $task->task_code . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', $task->title)
@section('breadcrumb', __('projects.title') . ' / ' . ($task->milestone?->name ?? $task->taskList?->name) . ' / ' . $task->task_code)

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">{{ __('projects.validation_errors') }}</h6>
                <ul class="mb-0 fs-12 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        @include('modules.projects.tasks.workspace._hero')
        @include('modules.projects.tasks.workspace._next-action')

        <div class="row g-4 mt-1">
            <div class="col-lg-8">
                @include('modules.projects.tasks.workspace._description')
                @include('modules.projects.tasks.workspace._subtasks')
                @include('modules.projects.tasks.workspace._dependencies')
                {{-- Future extension point: Comments and Attachments sections
                     will be added here, each its own partial, without
                     restructuring the page shell, hero, or rail. --}}
            </div>
            <div class="col-lg-4">
                @include('modules.projects.tasks.workspace._rail')
            </div>
        </div>

        <hr class="my-4">

        @include('modules.projects.tasks.workspace._activity')
    </div>

    @if ($canManageTask)
        @include('modules.projects.tasks._modal')
    @endif
@endsection
