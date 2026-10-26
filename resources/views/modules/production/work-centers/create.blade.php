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

        <form method="POST" action="{{ route('production.work-centers.store') }}">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.new_work_center') }}</h4>
                    <x-ui.button :href="route('production.work-centers.index')" variant="light" class="border">{{ __('production.cancel') }}</x-ui.button>
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
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.physical_location')" name="location" placeholder="{{ __('production.location_placeholder') }}" :value="old('location')" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.status')" name="status" :required="true">
                            <option value="active" @selected(old('status', 'active') === 'active')>{{ __('production.active_operating') }}</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>{{ __('production.inactive_suspended') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.overhead_cost') . ' (' . active_currency_symbol() . ')'" name="overhead_cost" inputType="number" step="0.01" placeholder="0.00" :value="old('overhead_cost', '0.00')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.calculation_countdown_minutes')" name="calculation_countdown_minutes" inputType="number" min="1" max="1440" step="1" placeholder="10" :value="old('calculation_countdown_minutes', 10)" :required="true" />

                        <x-ui.odoo-form-ui type="select" label="Production Calendar" name="production_calendar_id">
                            <option value="">-- Default Factory Calendar --</option>
                            @foreach($calendars as $cal)
                                <option value="{{ $cal->id }}" @selected(old('production_calendar_id') == $cal->id)>
                                    {{ $cal->name }}{{ $cal->is_default ? ' (Default)' : '' }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" placeholder="{{ __('production.wc_description_placeholder') }}" rows="4">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.save_work_center') }}</x-ui.button>
                    <x-ui.button :href="route('production.work-centers.index')" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
