@extends('layouts.duralux')

@section('title', 'Configure Quality Plan | SaaS ERP')
@section('page-title', 'Configure Quality Control Plan')
@section('breadcrumb', 'Configure Plan')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('production.quality-plans.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Configure New Quality Plan</h4>
                    <a href="{{ route('production.quality-plans.index') }}" class="text-muted hover-danger fs-18" aria-label="Close">
                        <i class="feather-x"></i>
                    </a>
                </div>

                @include('modules.production.quality.plans.partials.form', ['plan' => null])

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.quality-plans.index') }}" class="btn btn-light border">{{ __('production.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">Save as Draft</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
