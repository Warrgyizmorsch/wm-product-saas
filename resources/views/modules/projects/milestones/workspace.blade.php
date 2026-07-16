@extends('layouts.duralux')

@section('title', $milestone->name . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', $milestone->name)
@section('breadcrumb', __('projects.title') . ' / ' . __('projects.milestones') . ' / ' . $milestone->name)

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

        @include('modules.projects.milestones.workspace._hero')

        @php
            $workspaceTabs = [
                ['id' => 'tab-overview', 'label' => __('projects.workspace_overview'), 'icon' => 'feather-grid', 'active' => $activeTab === 'overview'],
                ['id' => 'tab-tasklists', 'label' => __('projects.tasklists'), 'icon' => 'feather-list', 'active' => $activeTab === 'tasklists'],
                ['id' => 'tab-timeline', 'label' => __('projects.workspace_timeline'), 'icon' => 'feather-calendar', 'active' => $activeTab === 'timeline'],
                ['id' => 'tab-activity', 'label' => __('projects.activity'), 'icon' => 'feather-activity', 'active' => $activeTab === 'activity'],
            ];
        @endphp
        <x-ui.horizontal-tabs id="milestoneWorkspaceTabs" :tabs="$workspaceTabs" />

        <div class="tab-content mt-3">
            <div class="tab-pane fade {{ $activeTab === 'overview' ? 'show active' : '' }}" id="tab-overview"
                role="tabpanel" aria-labelledby="tab-overview-tab">
                @include('modules.projects.milestones.workspace._overview')
            </div>
            <div class="tab-pane fade {{ $activeTab === 'tasklists' ? 'show active' : '' }}" id="tab-tasklists"
                role="tabpanel" aria-labelledby="tab-tasklists-tab">
                @include('modules.projects.milestones.workspace._tasklists')
            </div>
            <div class="tab-pane fade {{ $activeTab === 'timeline' ? 'show active' : '' }}" id="tab-timeline"
                role="tabpanel" aria-labelledby="tab-timeline-tab">
                @include('modules.projects.milestones.workspace._timeline')
            </div>
            <div class="tab-pane fade {{ $activeTab === 'activity' ? 'show active' : '' }}" id="tab-activity"
                role="tabpanel" aria-labelledby="tab-activity-tab">
                @include('modules.projects.milestones.workspace._activity')
            </div>
        </div>
    </div>

    @if ($canManageMilestones)
        @include('modules.projects.milestones._modal')
        @include('modules.projects.milestones._drawer')
    @endif

    @if ($canManageTaskLists)
        @include('modules.projects.tasklists._modal')
        @include('modules.projects.tasklists._drawer')
    @endif

    @if ($canCreateTasks)
        @include('modules.projects.tasks._modal')
        @include('modules.projects.tasks._drawer')
    @endif
@endsection
