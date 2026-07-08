@extends('layouts.duralux')

@section('title', 'Configure Shift | SaaS ERP')
@section('page-title', 'Configure Production Shift')
@section('breadcrumb', 'Configure Shift')

@section('content')
    <div class="erp-single-panel bg-white">
        <form method="POST" action="{{ route('production.shifts.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Configure New Shift</h4>
                    <a href="{{ route('production.shifts.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Shift Name" name="name" placeholder="e.g. Day Shift, Night Shift" :value="old('name')" :required="true" :error-text="$errors->first('name')" />
                        <x-ui.odoo-form-ui type="input" label="Shift Code" name="code" placeholder="e.g. DAY, NGT" :value="old('code')" :required="true" :error-text="$errors->first('code')" />
                        <x-ui.odoo-form-ui type="input" label="Start Time (HH:MM)" name="start_time" placeholder="e.g. 08:00" :value="old('start_time', '08:00')" :required="true" :error-text="$errors->first('start_time')" />
                    </div>

                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="End Time (HH:MM)" name="end_time" placeholder="e.g. 16:00" :value="old('end_time', '16:00')" :required="true" :error-text="$errors->first('end_time')" />
                        <x-ui.odoo-form-ui type="input" label="Break Minutes" name="break_minutes" inputType="number" placeholder="e.g. 30, 45" :value="old('break_minutes', '30')" :required="true" :error-text="$errors->first('break_minutes')" />
                        
                        <div class="mt-4 pt-2">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="overtime_allowed" id="overtime_allowed" value="1" @checked(old('overtime_allowed', true))>
                                <label class="form-check-label fw-semibold text-dark" for="overtime_allowed">Overtime Allowed</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', true))>
                                <label class="form-check-label fw-semibold text-dark" for="active">Active State</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.shifts.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Shift</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
