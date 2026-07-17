@extends('layouts.duralux')

@section('title', __('production.create_machine') . ' | SaaS ERP')
@section('page-title', __('production.create_machine'))
@section('breadcrumb', __('production.create_machine'))

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

        <form method="POST" action="{{ route('production.machines.store') }}">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.new_machine_asset') }}</h4>
                    <a href="{{ route('production.machines.index') }}" class="btn btn-sm btn-light border">{{ __('production.cancel') }}</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" :label="__('production.work_center_assignment')" name="work_center_id" :required="true">
                            <option value="">{{ __('production.select_work_center') }}</option>
                            @foreach($workCenters as $wc)
                                <option value="{{ $wc->id }}" @selected(old('work_center_id', $selectedWorkCenterId ?? '') == $wc->id)>
                                    {{ $wc->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_name')" name="name" placeholder="{{ __('production.machine_name_placeholder') }}" :value="old('name')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_asset_code')" name="code" placeholder="{{ __('production.machine_code_placeholder') }}" :value="old('code')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_type')" name="machine_type" placeholder="{{ __('production.machine_type_placeholder') }}" :value="old('machine_type')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.manufacturer')" name="manufacturer" placeholder="{{ __('production.manufacturer_placeholder') }}" :value="old('manufacturer')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.model_number')" name="model_number" placeholder="{{ __('production.model_placeholder') }}" :value="old('model_number')" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_hourly_capacity')" name="capacity" inputType="number" placeholder="{{ __('production.capacity_placeholder') }}" :value="old('capacity')" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.status')" name="status" :required="true">
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}" @selected(old('status', 'active') == $k)>{{ __('production.' . $k) ?? $v }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.installation_date')" name="installation_date" inputType="date" :value="old('installation_date')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.maintenance_details')" name="maintenance_status" placeholder="{{ __('production.maintenance_details_placeholder') }}" :value="old('maintenance_status')" />
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">{{ __('production.save_machine_asset') }}</button>
                    <a href="{{ route('production.machines.index') }}" class="btn btn-secondary px-4">{{ __('production.cancel') }}</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
