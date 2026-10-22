@extends('layouts.duralux')

@section('title', 'Edit Pay Grade | SaaS ERP')
@section('page-title', 'Edit External Worker Pay Grade')
@section('breadcrumb', 'Edit Pay Grade')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <form method="POST" action="{{ route('production.pay-grades.update', $payGrade->id) }}">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Edit External Worker Pay Grade: {{ $payGrade->name }}</h4>
                        <span class="fs-12 font-monospace text-muted">{{ $payGrade->code }}</span>
                    </div>
                    <a href="{{ route('production.pay-grades.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                @php
                    $activeAllocationsCount = $payGrade->activeAllocations()->count();
                @endphp
                @if($activeAllocationsCount > 0)
                    <div class="alert alert-info py-2 px-3 fs-12 mb-4 d-flex align-items-center">
                        <i class="feather-info me-2 fs-14"></i>
                        <span>This pay grade currently has <strong>{{ $activeAllocationsCount }} active allocation(s)</strong> across Section–Shifts. To delete this pay grade, all referencing allocations must be deactivated first.</span>
                    </div>
                @endif

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Pay Grade Code" name="code" placeholder="e.g. EXT-W1" :value="old('code', $payGrade->code)" :required="true" :error-text="$errors->first('code')" />
                        <x-ui.odoo-form-ui type="input" label="Pay Grade Name" name="name" placeholder="e.g. General Assembly Laborer" :value="old('name', $payGrade->name)" :required="true" :error-text="$errors->first('name')" />
                        <x-ui.odoo-form-ui type="input" label="Hourly Rate" name="hourly_rate" inputType="number" step="0.01" min="0" placeholder="e.g. 150.00" :value="old('hourly_rate', (float) $payGrade->hourly_rate)" :required="true" :error-text="$errors->first('hourly_rate')" />
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark fs-12">Description / Skill Expectations</label>
                            <textarea name="description" class="form-control form-control-sm" rows="4" placeholder="Optional details, job roles, or qualification requirements...">{{ old('description', $payGrade->description) }}</textarea>
                            @if ($errors->has('description'))
                                <span class="text-danger fs-11">{{ $errors->first('description') }}</span>
                            @endif
                        </div>
                        
                        <div class="mt-4 pt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', $payGrade->active))>
                                <label class="form-check-label fw-semibold text-dark" for="active">Active for Allocations</label>
                            </div>
                            <small class="text-muted fs-11 d-block mt-1">Inactive pay grades cannot be selected for new section-shift allocations.</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <div>
                        @can('delete', $payGrade)
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('Are you sure you want to delete this pay grade?')) { document.getElementById('delete-pay-grade-form').submit(); }">
                                <i class="feather-trash-2 me-1"></i>Delete Pay Grade
                            </button>
                        @endcan
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('production.pay-grades.index') }}" class="btn btn-light border">{{ __('production.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">Update Pay Grade</button>
                    </div>
                </div>
            </x-ui.odoo-form-ui>
        </form>

        <form id="delete-pay-grade-form" method="POST" action="{{ route('production.pay-grades.destroy', $payGrade->id) }}" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection
