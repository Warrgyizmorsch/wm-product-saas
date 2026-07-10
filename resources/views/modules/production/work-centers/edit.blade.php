@extends('layouts.duralux')

@section('title', 'Edit Work Center | SaaS ERP')
@section('page-title', 'Edit Work Center')
@section('breadcrumb', 'Edit Work Center')

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

        <form method="POST" action="{{ route('production.work-centers.update', $workCenter->id) }}">
            @csrf
            @method('PUT')
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Edit Work Center ({{ $workCenter->code }})</h4>
                    <a href="{{ route('production.work-centers.show', $workCenter->id) }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" label="Work Center Name" name="name" :value="old('name', $workCenter->name)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Work Center Code" name="code" :value="old('code', $workCenter->code)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" label="Work Center Type" name="work_center_type">
                            <option value="">Select Type</option>
                            @foreach($workCenterTypes as $k => $v)
                                <option value="{{ $k }}" @selected(old('work_center_type', $workCenter->work_center_type) == $k)>{{ $v }}</option>
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
                                <option value="{{ $id }}" @selected(old('parent_id', $workCenter->parent_id) == $id)>{{ $label }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="select" label="Hierarchy Type" name="type" :required="true">
                            <option value="department" @selected(old('type', $workCenter->type) === 'department')>Department</option>
                            <option value="section" @selected(old('type', $workCenter->type) === 'section')>Section</option>
                            <option value="work_center" @selected(old('type', $workCenter->type) === 'work_center')>Work Center</option>
                            <option value="machine_group" @selected(old('type', $workCenter->type) === 'machine_group')>Machine Group</option>
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" label="Department Name" name="department_name" :value="old('department_name', $workCenter->department_name)" />
                        
                        <x-ui.odoo-form-ui type="input" label="Physical Location" name="location" :value="old('location', $workCenter->location)" />
                        
                        <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true">
                            <option value="active" @selected(old('status', $workCenter->status) === 'active')>Active / Operating</option>
                            <option value="inactive" @selected(old('status', $workCenter->status) === 'inactive')>Inactive / Suspended</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Capacity Per Hour" name="capacity_per_hour" inputType="number" :value="old('capacity_per_hour', $workCenter->capacity_per_hour)" />
                        
                        <x-ui.odoo-form-ui type="input" label="Efficiency (%)" name="efficiency_percentage" inputType="number" :value="old('efficiency_percentage', $workCenter->efficiency_percentage)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Cost Per Hour ($)" name="cost_per_hour" inputType="number" :value="old('cost_per_hour', $workCenter->cost_per_hour)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="4">{{ old('description', $workCenter->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">Update Work Center</button>
                    <a href="{{ route('production.work-centers.show', $workCenter->id) }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
