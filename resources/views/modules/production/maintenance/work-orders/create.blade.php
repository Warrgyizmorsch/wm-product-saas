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
                                    <option value="breakdown" @selected(old('type') == 'breakdown')>{{ __('production.breakdown_machines') }}</option>
                                    <option value="calibration" @selected(old('type') == 'calibration')>Calibration</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Priority" name="priority" :required="true"
                                    :error-text="$errors->first('priority')">
                                    <option value="medium" @selected(old('priority', 'medium') == 'medium')>{{ __('production.priority_medium') }}</option>
                                    <option value="low" @selected(old('priority') == 'low')>{{ __('production.priority_low') }}</option>
                                    <option value="high" @selected(old('priority') == 'high')>{{ __('production.priority_high') }}</option>
                                    <option value="critical" @selected(old('priority') == 'critical')>{{ __('production.critical') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

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

                    <div class="col-12" x-data="{
                        rows: [],
                        addRow() {
                            this.rows.push({ assignment_type: 'internal', technician_id: '', technician_name: '', expected_work_hours: '', hourly_rate: '', notes: '' });
                        },
                        removeRow(index) {
                            if (this.rows.length > 0) this.rows.splice(index, 1);
                        }
                    }">
                        <div class="border rounded p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-dark mb-0">Assignments</h6>
                                    <span class="badge bg-soft-secondary text-secondary border" x-text="rows.length" x-show="rows.length > 0"></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" x-on:click="addRow()">
                                    <i class="feather-plus me-1"></i> Add Person
                                </button>
                            </div>

                            <template x-if="rows.length === 0">
                                <div class="border rounded p-3 bg-white text-muted fs-12">No technician assigned yet. Add people only if needed before starting the work order.</div>
                            </template>

                            <template x-for="(row, index) in rows" :key="index">
                                <div class="border rounded p-3 mb-3 bg-white">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <strong class="text-dark">Assignment #<span x-text="index + 1"></span></strong>
                                        <button type="button" class="btn btn-sm btn-outline-danger" x-on:click="removeRow(index)" x-show="rows.length > 0">Remove</button>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 text-muted fw-bold">Type</label>
                                            <select :name="'assignments[' + index + '][assignment_type]'" x-model="row.assignment_type" class="form-select form-select-sm">
                                                <option value="internal">Internal</option>
                                                <option value="external">External</option>
                                            </select>
                                        </div>

                                        <template x-if="row.assignment_type === 'internal'">
                                            <div class="col-md-4">
                                                <label class="form-label fs-12 text-muted fw-bold">Person</label>
                                                <select
                                                    :name="'assignments[' + index + '][technician_id]'"
                                                    x-model="row.technician_id"
                                                    x-on:change="
                                                        const option = $event.target.selectedOptions[0];
                                                        const autoRate = Number(option?.dataset?.rate ?? 0);
                                                        row.technician_name = option?.dataset?.name || '';
                                                        if (autoRate > 0) {
                                                            row.hourly_rate = autoRate;
                                                        }
                                                    "
                                                    class="form-select form-select-sm">
                                                    <option value="">Select employee</option>
                                                    @foreach($technicians as $tech)
                                                        <option value="{{ $tech->id }}" data-name="{{ $tech->name }}" data-rate="{{ $tech->calculated_hourly_rate ?? 0 }}">{{ $tech->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="hidden" :name="'assignments[' + index + '][technician_name]'" x-model="row.technician_name" />
                                            </div>
                                        </template>

                                        <template x-if="row.assignment_type === 'external'">
                                            <div class="col-md-4">
                                                <label class="form-label fs-12 text-muted fw-bold">Person</label>
                                                <input type="text" :name="'assignments[' + index + '][technician_name]'" x-model="row.technician_name" class="form-control form-control-sm" placeholder="Mechanic name" />
                                            </div>
                                        </template>

                                        <div class="col-md-2">
                                            <label class="form-label fs-12 text-muted fw-bold">Expected Hours</label>
                                            <input type="number" step="0.01" min="0" :name="'assignments[' + index + '][expected_work_hours]'" x-model="row.expected_work_hours" class="form-control form-control-sm" />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 text-muted fw-bold">Hourly Rate ({{ active_currency_symbol() }})</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">{{ active_currency_symbol() }}</span>
                                                <input type="number" step="0.01" min="0" :name="'assignments[' + index + '][hourly_rate]'" x-model="row.hourly_rate" class="form-control form-control-sm" placeholder="0.00" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs-12 text-muted fw-bold">Notes</label>
                                            <input type="text" :name="'assignments[' + index + '][notes]'" x-model="row.notes" class="form-control form-control-sm" placeholder="Optional notes" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.maintenance.work-orders.index') }}" class="btn btn-light border px-4">{{ __('production.cancel') }}</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i> Create Work Order</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
