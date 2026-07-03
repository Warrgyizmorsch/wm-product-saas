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
    <div class="erp-single-panel bg-white">
        <!-- Header with Close Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-0">Edit Production Plan - {{ $plan->plan_number }}</h4>
                <small class="text-muted">Status: <span class="text-uppercase fw-semibold text-primary">{{ $plan->status }}</span></small>
            </div>
            <a href="{{ route('production.plans.show', $plan->id) }}" class="text-muted hover-danger fs-18">
                <i class="feather-x"></i>
            </a>
        </div>

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
            
            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-md-6">
                    <x-ui.input label="Plan Name*" name="name" value="{{ old('name', $plan->name) }}" required />
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Item to Produce</label>
                        <select name="product_id" class="form-select" data-select2-selector="default" required>
                            <option value="{{ $plan->product_id }}">{{ $plan->product->name }} ({{ $plan->product->sku }})</option>
                        </select>
                        <small class="text-muted fs-11 mt-1 d-block">The item to produce cannot be changed after plan creation.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Bill of Materials (BOM)</label>
                        <select name="bom_id" class="form-select" data-select2-selector="default">
                            <option value="">None / Auto-select (Latest Approved)</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" {{ old('bom_id', $plan->bom_id) == $bom->id ? 'selected' : '' }}>
                                    {{ $bom->bom_number }} - {{ $bom->bom_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Routing</label>
                        <select name="routing_id" class="form-select" data-select2-selector="default">
                            <option value="">None / Auto-select (Default Active)</option>
                            @foreach($routings as $rt)
                                <option value="{{ $rt->id }}" {{ old('routing_id', $plan->routing_id) == $rt->id ? 'selected' : '' }}>
                                    {{ $rt->routing_number }} - {{ $rt->name }} (v{{ $rt->version }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <x-ui.input label="Target Quantity to Produce*" name="quantity" type="number" step="any" value="{{ old('quantity', $plan->quantity) }}" required />
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.input label="Planned Start Date*" name="start_date" type="date" value="{{ old('start_date', $plan->start_date->format('Y-m-d')) }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Planned End Date*" name="end_date" type="date" value="{{ old('end_date', $plan->end_date->format('Y-m-d')) }}" required />
                        </div>
                    </div>

                    <x-ui.textarea label="Description" name="description" value="{{ old('description', $plan->description) }}" rows="4" />
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="d-flex gap-2 pt-3 border-top mt-4">
                <button type="submit" class="btn btn-primary px-4">Update Plan</button>
                <a href="{{ route('production.plans.show', $plan->id) }}" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
@endsection
