@extends('layouts.duralux')

@section('title', __('projects.edit') . ' | SaaS ERP')
@section('page-title', __('projects.edit_code', ['code' => $project->project_code]))
@section('breadcrumb', __('projects.title') . ' / ' . __('projects.edit'))

@section('page-actions')
    <a href="{{ route('projects.show', $project->id) }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>{{ __('projects.back_to_project') }}
    </a>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="erp-single-panel">
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

        <form action="{{ route('projects.update', $project->id) }}" method="POST">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('projects.edit_code', ['code' => $project->project_code]) }}</h4>
                    <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold">
                        {{ __('projects.current_status', ['status' => __('projects.statuses.' . $project->status)]) }}
                    </span>
                </div>

                @include('modules.projects._form-fields', [
                    'statusOptions' => [
                        \App\Domains\Projects\Models\Project::STATUS_DRAFT,
                        \App\Domains\Projects\Models\Project::STATUS_ACTIVE,
                        \App\Domains\Projects\Models\Project::STATUS_ON_HOLD,
                        \App\Domains\Projects\Models\Project::STATUS_COMPLETED,
                    ],
                ])

                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="feather-check-circle me-2"></i>{{ __('projects.update_project') }}
                    </button>
                    <a href="{{ route('projects.show', $project->id) }}" class="btn btn-secondary px-4">{{ __('projects.cancel') }}</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
