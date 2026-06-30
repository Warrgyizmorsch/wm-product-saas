@extends('layouts.duralux')

@section('title', 'Edit Machine | SaaS ERP')
@section('page-title', 'Edit Machine')
@section('breadcrumb', 'Edit Machine')

@section('page-actions')
    <a href="{{ route('production.machines.index') }}" class="btn btn-secondary">
        <i class="feather-arrow-left me-2"></i>Back to List
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

    <form method="POST" action="{{ route('production.machines.update', $machine->id) }}">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                <x-ui.card title="Machine Master Details">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.select label="Work Center Assignment" name="work_center_id" :options="['' => 'Select Work Center'] + $workCenters->pluck('name', 'id')->toArray()" selected="{{ old('work_center_id', $machine->work_center_id) }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Machine Name" name="name" value="{{ old('name', $machine->name) }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Unique Machine Asset Code" name="code" value="{{ old('code', $machine->code) }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Machine Type / Category" name="machine_type" value="{{ old('machine_type', $machine->machine_type) }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Manufacturer" name="manufacturer" value="{{ old('manufacturer', $machine->manufacturer) }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Model Number" name="model_number" value="{{ old('model_number', $machine->model_number) }}" />
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <div class="col-xl-4">
                <x-ui.card title="Operational Settings">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Machine Hourly Capacity" name="capacity" type="number" step="any" value="{{ old('capacity', $machine->capacity) }}" helperText="Throughput per hour in standard units" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" :options="$statuses" selected="{{ old('status', $machine->status) }}" required />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Installation Date" name="installation_date" type="date" value="{{ old('installation_date', $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '') }}" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Maintenance Details" name="maintenance_status" value="{{ old('maintenance_status', $machine->maintenance_status) }}" />
                        </div>
                    </div>
                </x-ui.card>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="feather-save me-2"></i>Update Machine Asset
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection
