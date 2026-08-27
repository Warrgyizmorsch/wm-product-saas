@extends('layouts.duralux')

@section('title', __('production.create_work_order') ?? 'Create Work Order | SaaS ERP')
@section('page-title', __('production.create_work_order') ?? 'Create Work Order')
@section('breadcrumb', __('production.create_work_order') ?? 'Create Work Order')

@section('content')
    <div class="erp-single-panel bg-white">

        @if ($errors->any())
            <x-ui.toast :auto="true" type="error"
                title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        <form action="{{ route('production.maintenance.work-orders.store') }}" method="POST">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">New Maintenance Work Order</h4>
                        <p class="text-muted fs-12 mb-0">Create a new preventive, breakdown, or calibration maintenance ticket</p>
                    </div>
                    <a href="{{ route('production.maintenance.work-orders.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Machine" name="machine_id" :required="true"
                            :error-text="$errors->first('machine_id')">
                            <option value="">Select Machine</option>
                            @foreach($machines as $m)
                                <option value="{{ $m->id }}" @selected(old('machine_id') == $m->id)>{{ $m->name }} ({{ $m->code }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="WO Type" name="type" :required="true"
                                    :error-text="$errors->first('type')">
                                    <option value="preventive" @selected(old('type') == 'preventive')>Preventive</option>
                                    <option value="breakdown" @selected(old('type') == 'breakdown')>Breakdown</option>
                                    <option value="calibration" @selected(old('type') == 'calibration')>Calibration</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Priority" name="priority" :required="true"
                                    :error-text="$errors->first('priority')">
                                    <option value="medium" @selected(old('priority', 'medium') == 'medium')>Medium</option>
                                    <option value="low" @selected(old('priority') == 'low')>Low</option>
                                    <option value="high" @selected(old('priority') == 'high')>High</option>
                                    <option value="critical" @selected(old('priority') == 'critical')>Critical</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <x-ui.odoo-form-ui type="select" label="Assigned Technician" name="assigned_technician_id"
                            :error-text="$errors->first('assigned_technician_id')">
                            <option value="">Unassigned</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}" @selected(old('assigned_technician_id') == $tech->id)>{{ $tech->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Planned Start Time" name="planned_start"
                            :value="old('planned_start')" :error-text="$errors->first('planned_start')" />

                        <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Planned End Time" name="planned_end"
                            :value="old('planned_end')" :error-text="$errors->first('planned_end')" />
                    </div>

                    <div class="col-12">
                        <x-ui.odoo-form-ui type="textarea" label="Problem Description / Maintenance Scope" name="problem_description"
                            rows="3" :required="true" placeholder="Describe the maintenance requirement, symptoms, or repair task details..."
                            :error-text="$errors->first('problem_description')">{{ old('problem_description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.maintenance.work-orders.index') }}" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i> Create Work Order</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
