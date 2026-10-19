@extends('layouts.duralux')

@section('title', __('production.edit_work_center') . ' | SaaS ERP')
@section('page-title', __('production.edit_work_center'))
@section('breadcrumb', __('production.edit_work_center'))

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

        <form method="POST" action="{{ route('production.work-centers.update', $workCenter->id) }}">
            @csrf
            @method('PUT')
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.edit_work_center_with_name', ['name' => $workCenter->code]) }}</h4>
                    <x-ui.button :href="route('production.work-centers.show', $workCenter->id)" variant="light" class="border">{{ __('production.cancel') }}</x-ui.button>
                </div>

                @if ($errors->has('confirm_section_shift_removal'))
                    <div class="alert alert-warning border border-warning d-flex align-items-start gap-3 mb-4 shadow-sm">
                        <i class="feather-alert-triangle fs-20 text-warning mt-1 flex-shrink-0"></i>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold text-dark mb-1">{{ __('production.shift_removal_affects_sections') }}</h6>
                            <p class="text-dark fs-13 mb-3">{{ $errors->first('confirm_section_shift_removal') }}</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirm_section_shift_removal" value="1" id="confirm_section_shift_removal" required>
                                <label class="form-check-label fw-bold text-dark fs-13" for="confirm_section_shift_removal">
                                    {{ __('production.confirm_cascade_shift_removal') }}
                                </label>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($errors->has('shifts'))
                    <div class="alert alert-danger border border-danger d-flex align-items-start gap-3 mb-4 shadow-sm">
                        <i class="feather-alert-circle fs-20 text-danger mt-1 flex-shrink-0"></i>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold text-danger mb-1">{{ __('production.shift_removal_blocked') }}</h6>
                            <p class="text-dark fs-13 mb-0">{{ $errors->first('shifts') }}</p>
                        </div>
                    </div>
                @endif

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.work_center_name')" name="name" :value="old('name', $workCenter->name)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.work_center_code')" name="code" :value="old('code', $workCenter->code)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.work_center_type')" name="work_center_type">
                            <option value="">{{ __('production.select_type') }}</option>
                            @foreach($workCenterTypes as $k => $v)
                                <option value="{{ $k }}" @selected(old('work_center_type', $workCenter->work_center_type) == $k)>{{ $v }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.physical_location')" name="location" :value="old('location', $workCenter->location)" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.status')" name="status" :required="true">
                            <option value="active" @selected(old('status', $workCenter->status) === 'active')>{{ __('production.active_operating') }}</option>
                            <option value="inactive" @selected(old('status', $workCenter->status) === 'inactive')>{{ __('production.inactive_suspended') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.capacity_per_hour')" name="capacity_per_hour" inputType="number" :value="old('capacity_per_hour', $workCenter->capacity_per_hour)" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.efficiency')" name="efficiency_percentage" inputType="number" :value="old('efficiency_percentage', $workCenter->efficiency_percentage)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.cost_per_hour') . ' (' . active_currency_symbol() . ')'" name="cost_per_hour" inputType="number" step="0.01" :value="old('cost_per_hour', number_format(convert_from_base($workCenter->cost_per_hour), 2, '.', ''))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.overhead_cost_rate') . ' (' . active_currency_symbol() . ')'" name="overhead_rate" inputType="number" step="0.01" :value="old('overhead_rate', number_format(convert_from_base($workCenter->overhead_rate ?? 0), 2, '.', ''))" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.active_shifts')" name="shifts[]" :multiple="true" :searchable="true" :errorText="$errors->first('shifts')">
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}" @selected(in_array($shift->id, old('shifts', $workCenter->shifts->pluck('id')->toArray())))>
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="select" label="Production Calendar" name="production_calendar_id">
                            <option value="">-- Default Factory Calendar --</option>
                            @foreach($calendars as $cal)
                                <option value="{{ $cal->id }}" @selected(old('production_calendar_id', $workCenter->production_calendar_id) == $cal->id)>
                                    {{ $cal->name }}{{ $cal->is_default ? ' (Default)' : '' }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" rows="4">{{ old('description', $workCenter->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.update_work_center') }}</x-ui.button>
                    <x-ui.button :href="route('production.work-centers.show', $workCenter->id)" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
