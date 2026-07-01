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
    <div class="erp-single-panel bg-white">
        <!-- Header with Close Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">Edit Work Center ({{ $workCenter->code }})</h4>
            <a href="{{ route('production.work-centers.show', $workCenter->id) }}" class="text-muted hover-danger fs-18">
                <i class="feather-x"></i>
            </a>
        </div>

        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Validation Errors!</h6>
                <ul class="mb-0 fs-12 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        <form method="POST" action="{{ route('production.work-centers.update', $workCenter->id) }}">
            @csrf
            @method('PUT')
            
            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-md-6">
                    <x-ui.input label="Work Center Name*" name="name" value="{{ old('name', $workCenter->name) }}" required />
                    
                    <x-ui.input label="Work Center Code*" name="code" value="{{ old('code', $workCenter->code) }}" required />
                    
                    <x-ui.select label="Work Center Type" name="work_center_type" :options="['' => 'Select Type'] + $workCenterTypes" selected="{{ old('work_center_type', $workCenter->work_center_type) }}" data-select2-selector="default" />
                    
                    @php
                        $parentList = ['' => 'None / Top Level'];
                        foreach ($parentOptions as $po) {
                            $parentList[$po->id] = "{$po->name} ({$po->code})";
                        }
                    @endphp
                    <x-ui.select label="Parent Work Center" name="parent_id" :options="$parentList" selected="{{ old('parent_id', $workCenter->parent_id) }}" data-select2-selector="default" />
                    
                    <x-ui.select label="Hierarchy Type*" name="type" :options="[
                        'department' => 'Department',
                        'section' => 'Section',
                        'work_center' => 'Work Center',
                        'machine_group' => 'Machine Group'
                    ]" selected="{{ old('type', $workCenter->type ?? 'work_center') }}" data-select2-selector="default" required />
                    
                    <x-ui.input label="Department Name" name="department_name" value="{{ old('department_name', $workCenter->department_name) }}" />
                    
                    <x-ui.input label="Physical Location" name="location" value="{{ old('location', $workCenter->location) }}" />
                    
                    <x-ui.select label="Status*" name="status" :options="[
                        'active' => 'Active / Operating',
                        'inactive' => 'Inactive / Suspended'
                    ]" selected="{{ old('status', $workCenter->status) }}" data-select2-selector="default" required />
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <x-ui.input label="Capacity Per Hour" name="capacity_per_hour" type="number" step="any" placeholder="e.g. 50.00" value="{{ old('capacity_per_hour', $workCenter->capacity_per_hour) }}" helperText="Leave empty for unlimited/flexible capacity" />
                    
                    <x-ui.input label="Efficiency (%)*" name="efficiency_percentage" type="number" step="any" placeholder="100.00" value="{{ old('efficiency_percentage', $workCenter->efficiency_percentage) }}" required />
                    
                    <x-ui.input label="Cost Per Hour ($)*" name="cost_per_hour" type="number" step="any" placeholder="0.0000" value="{{ old('cost_per_hour', $workCenter->cost_per_hour) }}" required />
                    
                    <x-ui.textarea label="Description" name="description" placeholder="Enter purpose, operational limits, or other description..." value="{{ old('description', $workCenter->description) }}" rows="4" />
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="d-flex gap-2 pt-3 border-top mt-4">
                <button type="submit" class="btn btn-primary px-4">Update Work Center</button>
                <a href="{{ route('production.work-centers.show', $workCenter->id) }}" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
@endsection
