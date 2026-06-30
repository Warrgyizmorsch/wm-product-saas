@extends('layouts.duralux')

@section('title', 'Create Work Center | SaaS ERP')
@section('page-title', 'Create Work Center')
@section('breadcrumb', 'Create Work Center')

@section('page-actions')
    <a href="{{ route('production.work-centers.index') }}" class="btn btn-secondary">
        <i class="feather-x me-2"></i>Cancel
    </a>
@endsection

@section('content')
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

    <form method="POST" action="{{ route('production.work-centers.store') }}">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <x-ui.card title="Work Center Details">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.input label="Work Center Name" name="name" placeholder="e.g. Assembly Line A" value="{{ old('name') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Unique Work Center Code" name="code" placeholder="e.g. WC-ASSY-A" value="{{ old('code') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Work Center Type" name="work_center_type" :options="['' => 'Select Type'] + $workCenterTypes" selected="{{ old('work_center_type') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Department Name" name="department_name" placeholder="e.g. Production Department" value="{{ old('department_name') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Physical Location" name="location" placeholder="e.g. Plant 1, Floor 2" value="{{ old('location') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Status" name="status" :options="[
                                'active' => 'Active / Operating',
                                'inactive' => 'Inactive / Suspended'
                            ]" selected="{{ old('status', 'active') }}" required />
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2">Detailed Description</label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Enter purpose, operational limits, or other description...">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <div class="col-xl-4">
                <x-ui.card title="Capacity & Cost Settings">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Capacity Per Hour (Units)" name="capacity_per_hour" type="number" step="any" placeholder="e.g. 50.00" value="{{ old('capacity_per_hour') }}" helperText="Leave empty for unlimited/flexible capacity" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Efficiency Percentage (%)" name="efficiency_percentage" type="number" step="any" placeholder="100.00" value="{{ old('efficiency_percentage', '100.00') }}" required />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Overhead Cost Per Hour ($)" name="cost_per_hour" type="number" step="any" placeholder="0.0000" value="{{ old('cost_per_hour', '0.0000') }}" required />
                        </div>
                    </div>
                </x-ui.card>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="feather-save me-2"></i>Save Work Center
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection
