@extends('layouts.duralux')

@section('title', __('production.edit_pm_schedule') ?? 'Edit PM Schedule | SaaS ERP')
@section('page-title', __('production.edit_pm_schedule') ?? 'Edit PM Schedule')
@section('breadcrumb', __('production.edit_pm_schedule') ?? 'Edit PM Schedule')

@section('content')
    <div class="erp-single-panel bg-white">

        @if ($errors->any())
            <x-ui.toast :auto="true" type="error"
                title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        <form action="{{ route('production.maintenance.schedules.update', $schedule->id) }}" method="POST">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">Edit PM Schedule: {{ $schedule->code }}</h4>
                        <p class="text-muted fs-12 mb-0">Update recurring maintenance rules and frequency settings</p>
                    </div>
                    <a href="{{ route('production.maintenance.schedules.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Machine" name="machine_id" :required="true"
                            :error-text="$errors->first('machine_id')">
                            @foreach($machines as $m)
                                <option value="{{ $m->id }}" @selected(old('machine_id', $schedule->machine_id) == $m->id)>{{ $m->name }} ({{ $m->code }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="Schedule Name" name="name" :value="old('name', $schedule->name)"
                            :required="true" :error-text="$errors->first('name')" />

                        <x-ui.odoo-form-ui type="select" label="Maintenance Type" name="maintenance_type" :required="true"
                            :error-text="$errors->first('maintenance_type')">
                            <option value="preventive" @selected(old('maintenance_type', $schedule->maintenance_type) == 'preventive')>Preventive</option>
                            <option value="calibration" @selected(old('maintenance_type', $schedule->maintenance_type) == 'calibration')>Calibration</option>
                            <option value="inspection" @selected(old('maintenance_type', $schedule->maintenance_type) == 'inspection')>Inspection</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Priority" name="priority" :required="true"
                            :error-text="$errors->first('priority')">
                            <option value="medium" @selected(old('priority', $schedule->priority) == 'medium')>Medium</option>
                            <option value="low" @selected(old('priority', $schedule->priority) == 'low')>Low</option>
                            <option value="high" @selected(old('priority', $schedule->priority) == 'high')>High</option>
                            <option value="critical" @selected(old('priority', $schedule->priority) == 'critical')>Critical</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-md-6">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Frequency Unit" name="frequency_type" :required="true"
                                    :error-text="$errors->first('frequency_type')">
                                    <option value="days" @selected(old('frequency_type', $schedule->frequency_type) == 'days')>Days</option>
                                    <option value="weeks" @selected(old('frequency_type', $schedule->frequency_type) == 'weeks')>Weeks</option>
                                    <option value="months" @selected(old('frequency_type', $schedule->frequency_type) == 'months')>Months</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" label="Frequency Value" name="frequency_value"
                                    :value="old('frequency_value', $schedule->frequency_value)" min="1" :required="true"
                                    :error-text="$errors->first('frequency_value')" />
                            </div>
                        </div>

                        <x-ui.odoo-form-ui type="input" inputType="number" step="0.1" label="Estimated Duration (Hours)"
                            name="estimated_duration_hours" :value="old('estimated_duration_hours', $schedule->estimated_duration_hours)" min="0.1" :required="true"
                            :error-text="$errors->first('estimated_duration_hours')" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Last Completed Date" name="last_completed_date"
                            :value="old('last_completed_date', $schedule->last_completed_date?->format('Y-m-d'))"
                            :error-text="$errors->first('last_completed_date')" />
                    </div>

                    <div class="col-12">
                        <x-ui.odoo-form-ui type="textarea" label="Checklist Items (One per line)" name="checklist_text"
                            rows="4" :error-text="$errors->first('checklist_text')">{{ old('checklist_text', is_array($schedule->checklist_json) ? implode(PHP_EOL, $schedule->checklist_json) : '') }}</x-ui.odoo-form-ui>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $schedule->is_active))>
                            <label class="form-check-label fw-bold text-dark" for="is_active">Active PM Schedule</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.maintenance.schedules.index') }}" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i> Update PM Schedule</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
