@extends('layouts.duralux')

@section('title', 'Edit Production Plan | SaaS ERP')
@section('page-title', 'Edit Production Plan')
@section('breadcrumb', 'Edit Production Plan')

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

        <form method="POST" action="{{ route('production.plans.update', $plan->id) }}">
            @csrf
            @method('PUT')
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Edit Production Plan - {{ $plan->plan_number }}</h4>
                        <small class="text-muted">Status: <span class="text-uppercase fw-semibold text-primary">{{ $plan->status }}</span></small>
                    </div>
                    <a href="{{ route('production.plans.show', $plan->id) }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" label="Plan Name" name="name" :value="old('name', $plan->name)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" label="Item to Produce" name="product_id" :required="true">
                            <option value="{{ $plan->product_id }}">{{ $plan->product->name }} ({{ $plan->product->sku }})</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Bill of Materials" name="bom_id">
                            <option value="">None / Auto-select (Latest Approved)</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" @selected(old('bom_id', $plan->bom_id) == $bom->id)>
                                    {{ $bom->bom_number }} - {{ $bom->bom_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Routing" name="routing_id">
                            <option value="">None / Auto-select (Default Active)</option>
                            @foreach($routings as $rt)
                                <option value="{{ $rt->id }}" @selected(old('routing_id', $plan->routing_id) == $rt->id)>
                                    {{ $rt->routing_number }} - {{ $rt->name }} (v{{ $rt->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Target Quantity" name="quantity" inputType="number" :value="old('quantity', $plan->quantity)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Planned Start Date" name="start_date" inputType="date" :value="old('start_date', $plan->start_date->format('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Planned End Date" name="end_date" inputType="date" :value="old('end_date', $plan->end_date->format('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="4">{{ old('description', $plan->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">Update Plan</button>
                    <a href="{{ route('production.plans.show', $plan->id) }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
