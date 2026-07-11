@extends('layouts.duralux')

@section('title', 'Create Work Center | SaaS ERP')
@section('page-title', 'Create Work Center')
@section('breadcrumb', 'Create Work Center')

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
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.work-centers.store') }}">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">New Work Center</h4>
                    <a href="{{ route('production.work-centers.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" label="Work Center Name" name="name" placeholder="e.g. Assembly Line A" :value="old('name')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Work Center Code" name="code" placeholder="e.g. WC-ASSY-A" :value="old('code')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" label="Work Center Type" name="work_center_type">
                            <option value="">Select Type</option>
                            @foreach($workCenterTypes as $k => $v)
                                <option value="{{ $k }}" @selected(old('work_center_type') == $k)>{{ $v }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        @php
                            $parentList = [];
                            foreach ($parentOptions as $po) {
                                $parentList[$po->id] = "{$po->name} ({$po->code})";
                            }
                        @endphp
                        <x-ui.odoo-form-ui type="select" label="Parent Work Center" name="parent_id">
                            <option value="">None / Top Level</option>
                            @foreach($parentList as $id => $label)
                                <option value="{{ $id }}" @selected(old('parent_id') == $id)>{{ $label }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="select" label="Hierarchy Type" name="type" :required="true">
                            <option value="department" @selected(old('type') === 'department')>Department</option>
                            <option value="section" @selected(old('type') === 'section')>Section</option>
                            <option value="work_center" @selected(old('type', 'work_center') === 'work_center')>Work Center</option>
                            <option value="machine_group" @selected(old('type') === 'machine_group')>Machine Group</option>
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" label="Department Name" name="department_name" placeholder="e.g. Production Department" :value="old('department_name')" />
                        
                        <x-ui.odoo-form-ui type="input" label="Physical Location" name="location" placeholder="e.g. Plant 1, Floor 2" :value="old('location')" />
                        
                        <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true">
                            <option value="active" @selected(old('status', 'active') === 'active')>Active / Operating</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive / Suspended</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Capacity Per Hour" name="capacity_per_hour" inputType="number" placeholder="e.g. 50.00" :value="old('capacity_per_hour')" />
                        
                        <x-ui.odoo-form-ui type="input" label="Efficiency (%)" name="efficiency_percentage" inputType="number" placeholder="100.00" :value="old('efficiency_percentage', '100.00')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Cost Per Hour ($)" name="cost_per_hour" inputType="number" placeholder="0.0000" :value="old('cost_per_hour', '0.0000')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" label="Active Shifts" name="shifts[]" :multiple="true" :searchable="true">
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}" @selected(in_array($shift->id, old('shifts', [])))>
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Enter purpose, operational limits, or other description..." rows="4">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">Save Work Center</button>
                    <a href="{{ route('production.work-centers.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
