@extends('layouts.duralux')

@section('title', 'Create Pay Grade | SaaS ERP')
@section('page-title', 'Create External Worker Pay Grade')
@section('breadcrumb', 'Create Pay Grade')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.pay-grades.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Create External Worker Pay Grade</h4>
                    <a href="{{ route('production.pay-grades.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Pay Grade Code" name="code" placeholder="e.g. EXT-W1, SR-OPERATOR" :value="old('code')" :required="true" :error-text="$errors->first('code')" />
                        <x-ui.odoo-form-ui type="input" label="Pay Grade Name" name="name" placeholder="e.g. General Assembly Laborer, Certified Welder" :value="old('name')" :required="true" :error-text="$errors->first('name')" />
                        <x-ui.odoo-form-ui type="input" label="Hourly Rate" name="hourly_rate" inputType="number" step="0.01" min="0" placeholder="e.g. 150.00" :value="old('hourly_rate', '0.00')" :required="true" :error-text="$errors->first('hourly_rate')" />
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark fs-12">Description / Skill Expectations</label>
                            <textarea name="description" class="form-control form-control-sm" rows="4" placeholder="Optional details, job roles, or qualification requirements...">{{ old('description') }}</textarea>
                            @if ($errors->has('description'))
                                <span class="text-danger fs-11">{{ $errors->first('description') }}</span>
                            @endif
                        </div>
                        
                        <div class="mt-4 pt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', true))>
                                <label class="form-check-label fw-semibold text-dark" for="active">Active for Allocations</label>
                            </div>
                            <small class="text-muted fs-11 d-block mt-1">Inactive pay grades cannot be selected for new section-shift allocations.</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.pay-grades.index') }}" class="btn btn-light border">{{ __('production.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">Save Pay Grade</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
