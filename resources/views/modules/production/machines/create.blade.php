@extends('layouts.duralux')

@section('title', 'Create Machine | SaaS ERP')
@section('page-title', 'Create Machine')
@section('breadcrumb', 'Create Machine')

@section('page-actions')
    <a href="{{ route('production.machines.index') }}" class="btn btn-secondary">
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

    <form method="POST" action="{{ route('production.machines.store') }}">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <x-ui.card title="Machine Master Details">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.select label="Work Center Assignment" name="work_center_id" :options="['' => 'Select Work Center'] + $workCenters->pluck('name', 'id')->toArray()" selected="{{ old('work_center_id', $selectedWorkCenterId ?? '') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Machine Name" name="name" placeholder="e.g. Laser Cutter 3" value="{{ old('name') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Unique Machine Asset Code" name="code" placeholder="e.g. MCH-LSR-03" value="{{ old('code') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Machine Type / Category" name="machine_type" placeholder="e.g. CNC Laser" value="{{ old('machine_type') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Manufacturer" name="manufacturer" placeholder="e.g. Trumpf" value="{{ old('manufacturer') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Model Number" name="model_number" placeholder="e.g. TruLaser 3030" value="{{ old('model_number') }}" />
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <div class="col-xl-4">
                <x-ui.card title="Operational Settings">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Machine Hourly Capacity" name="capacity" type="number" step="any" placeholder="e.g. 100.00" value="{{ old('capacity') }}" helperText="Throughput per hour in standard units" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" :options="$statuses" selected="{{ old('status', 'active') }}" required />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Installation Date" name="installation_date" type="date" value="{{ old('installation_date') }}" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Maintenance Details" name="maintenance_status" placeholder="e.g. Normal, Scheduled" value="{{ old('maintenance_status') }}" />
                        </div>
                    </div>
                </x-ui.card>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="feather-save me-2"></i>Save Machine Asset
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection
