@extends('layouts.duralux')

@section('title', 'Create Calendar | SaaS ERP')
@section('page-title', 'Create Production Calendar')
@section('breadcrumb', 'Create Calendar')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <form method="POST" action="{{ route('production.calendars.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Create Production Calendar</h4>
                    <a href="{{ route('production.calendars.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Calendar Name" name="name" placeholder="e.g. Standard Weekday Calendar" :value="old('name')" :required="true" :error-text="$errors->first('name')" />
                        
                        <div class="mt-4 pt-2">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1" @checked(old('is_default', false))>
                                <label class="form-check-label fw-semibold text-dark" for="is_default">Mark as Default System Calendar</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark mb-2">Select Working Days</label>
                        <div class="border rounded p-3 bg-light">
                            @php
                                $daysOfWeek = [
                                    1 => 'Monday',
                                    2 => 'Tuesday',
                                    3 => 'Wednesday',
                                    4 => 'Thursday',
                                    5 => 'Friday',
                                    6 => 'Saturday',
                                    0 => 'Sunday'
                                ];
                                $oldWorkingDays = old('working_days', [1, 2, 3, 4, 5]); // default Mon-Fri
                            @endphp
                            @foreach($daysOfWeek as $value => $label)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="working_days[]" id="day_{{ $value }}" value="{{ $value }}" @checked(in_array($value, $oldWorkingDays))>
                                    <label class="form-check-label fw-medium text-dark" for="day_{{ $value }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                            @error('working_days')
                                <span class="text-danger fs-11 mt-1 d-block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.calendars.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Calendar</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
