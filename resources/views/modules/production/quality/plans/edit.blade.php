@extends('layouts.duralux')

@section('title', 'Edit Quality Plan | SaaS ERP')
@section('page-title', 'Edit Quality Control Plan')
@section('breadcrumb', 'Edit Plan')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('production.quality-plans.update', $plan->id) }}">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">
                        Edit Quality Plan — {{ $plan->name }}
                        <span class="text-muted fs-13 fw-normal ms-1">{{ $plan->plan_number }} · v{{ $plan->version }}</span>
                    </h4>
                    <a href="{{ route('production.quality-plans.show', $plan->id) }}" class="text-muted hover-danger fs-18" aria-label="Close">
                        <i class="feather-x"></i>
                    </a>
                </div>

                @include('modules.production.quality.plans.partials.form', ['plan' => $plan])

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.quality-plans.show', $plan->id) }}" class="btn btn-light border">{{ __('production.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        {{ $plan->isApproved() ? 'Save as New Version' : 'Update Draft' }}
                    </button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
