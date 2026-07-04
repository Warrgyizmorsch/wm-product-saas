@extends('layouts.duralux')

@section('title', 'Edit Machine | SaaS ERP')
@section('page-title', 'Edit Machine')
@section('breadcrumb', 'Edit Machine')

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
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Edit Machine Asset ({{ $machine->code }})</h4>
                    <a href="{{ route('production.machines.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" label="Work Center Assignment" name="work_center_id" :required="true">
                            <option value="">Select Work Center</option>
                            @foreach($workCenters as $wc)
                                <option value="{{ $wc->id }}" @selected(old('work_center_id', $machine->work_center_id) == $wc->id)>
                                    {{ $wc->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" label="Machine Name" name="name" :value="old('name', $machine->name)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Machine Asset Code" name="code" :value="old('code', $machine->code)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Machine Type / Category" name="machine_type" :value="old('machine_type', $machine->machine_type)" />
                        
                        <x-ui.odoo-form-ui type="input" label="Manufacturer" name="manufacturer" :value="old('manufacturer', $machine->manufacturer)" />
                        
                        <x-ui.odoo-form-ui type="input" label="Model Number" name="model_number" :value="old('model_number', $machine->model_number)" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Machine Hourly Capacity" name="capacity" inputType="number" :value="old('capacity', $machine->capacity)" />
                        
                        <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true">
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}" @selected(old('status', $machine->status) == $k)>{{ $v }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" label="Installation Date" name="installation_date" inputType="date" :value="old('installation_date', $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '')" />
                        
                        <x-ui.odoo-form-ui type="input" label="Maintenance Details" name="maintenance_status" :value="old('maintenance_status', $machine->maintenance_status)" />
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">Update Machine Asset</button>
                    <a href="{{ route('production.machines.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
