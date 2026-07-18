@extends('layouts.duralux')

@section('title', __('production.create_work_center') . ' | SaaS ERP')
@section('page-title', __('production.create_work_center'))
@section('breadcrumb', __('production.create_work_center'))

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
            <x-ui.toast :auto="true" type="error" title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.work-centers.store') }}">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.new_work_center') }}</h4>
                    <a href="{{ route('production.work-centers.index') }}" class="btn btn-sm btn-light border">{{ __('production.cancel') }}</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.work_center_name')" name="name" placeholder="{{ __('production.wc_name_placeholder') }}" :value="old('name')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.work_center_code')" name="code" placeholder="{{ __('production.wc_code_placeholder') }}" :value="old('code')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.work_center_type')" name="work_center_type">
                            <option value="">{{ __('production.select_type') }}</option>
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
                        <x-ui.odoo-form-ui type="select" :label="__('production.parent_work_center')" name="parent_id">
                            <option value="">{{ __('production.none_top_level') }}</option>
                            @foreach($parentList as $id => $label)
                                <option value="{{ $id }}" @selected(old('parent_id') == $id)>{{ $label }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.hierarchy_type')" name="type" :required="true">
                            <option value="department" @selected(old('type') === 'department')>{{ __('production.wc_type_department') }}</option>
                            <option value="section" @selected(old('type') === 'section')>{{ __('production.wc_type_section') }}</option>
                            <option value="work_center" @selected(old('type', 'work_center') === 'work_center')>{{ __('production.wc_type_work_center') }}</option>
                            <option value="machine_group" @selected(old('type') === 'machine_group')>{{ __('production.wc_type_machine_group') }}</option>
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.department_name')" name="department_name" placeholder="{{ __('production.dept_name_placeholder') }}" :value="old('department_name')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.physical_location')" name="location" placeholder="{{ __('production.location_placeholder') }}" :value="old('location')" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.status')" name="status" :required="true">
                            <option value="active" @selected(old('status', 'active') === 'active')>{{ __('production.active_operating') }}</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>{{ __('production.inactive_suspended') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.capacity_per_hour')" name="capacity_per_hour" inputType="number" placeholder="{{ __('production.capacity_placeholder') }}" :value="old('capacity_per_hour')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.efficiency')" name="efficiency_percentage" inputType="number" placeholder="{{ __('production.efficiency_placeholder') }}" :value="old('efficiency_percentage', '100.00')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.cost_per_hour')" name="cost_per_hour" inputType="number" placeholder="{{ __('production.cost_placeholder') }}" :value="old('cost_per_hour', '0.0000')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.active_shifts')" name="shifts[]" :multiple="true" :searchable="true">
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}" @selected(in_array($shift->id, old('shifts', [])))>
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" placeholder="{{ __('production.wc_description_placeholder') }}" rows="4">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">{{ __('production.save_work_center') }}</button>
                    <a href="{{ route('production.work-centers.index') }}" class="btn btn-secondary px-4">{{ __('production.cancel') }}</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
