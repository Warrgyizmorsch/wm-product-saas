@extends('layouts.duralux')

@section('title', 'Edit Shift | SaaS ERP')
@section('page-title', 'Edit Production Shift')
@section('breadcrumb', 'Edit Shift')

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

        <form method="POST" action="{{ route('production.shifts.update', $shift->id) }}">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Edit Shift - {{ $shift->code }}</h4>
                    <a href="{{ route('production.shifts.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Shift Name" name="name" placeholder="e.g. Day Shift, Night Shift" :value="old('name', $shift->name)" :required="true" :error-text="$errors->first('name')" />
                        <x-ui.odoo-form-ui type="input" label="Shift Code" name="code" placeholder="e.g. DAY, NGT" :value="old('code', $shift->code)" :required="true" :error-text="$errors->first('code')" />
                        <x-ui.odoo-form-ui type="input" label="Start Time (HH:MM)" name="start_time" placeholder="e.g. 08:00" :value="old('start_time', substr($shift->start_time, 0, 5))" :required="true" :error-text="$errors->first('start_time')" />
                    </div>

                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="End Time (HH:MM)" name="end_time" placeholder="e.g. 16:00" :value="old('end_time', substr($shift->end_time, 0, 5))" :required="true" :error-text="$errors->first('end_time')" />
                        <x-ui.odoo-form-ui type="input" label="Break Minutes" name="break_minutes" inputType="number" placeholder="e.g. 30, 45" :value="old('break_minutes', $shift->break_minutes)" :required="true" :error-text="$errors->first('break_minutes')" />
                        
                        <div class="mt-4 pt-2">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="overtime_allowed" id="overtime_allowed" value="1" @checked(old('overtime_allowed', $shift->overtime_allowed))>
                                <label class="form-check-label fw-semibold text-dark" for="overtime_allowed">Overtime Allowed</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', $shift->active))>
                                <label class="form-check-label fw-semibold text-dark" for="active">Active State</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.shifts.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Shift</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
